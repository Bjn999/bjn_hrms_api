<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionUpgradeRequest;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SubscriptionService
{
    /**
     * Get and sync current subscription status for a company in real-time.
     */
    public static function getCompanySubscriptionStatus(string $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company) {
            return [
                'exists' => false,
                'is_active' => false,
                'is_expired' => true,
                'subscription' => null,
            ];
        }

        $tz = $company->timezone ?: config('app.timezone', 'Asia/Riyadh');
        $now = Carbon::now($tz);

        // Fetch current active or latest subscription
        $subscription = Subscription::with('plan')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->latest('id')
            ->first();

        if (!$subscription) {
            // Fallback to latest subscription record (might be expired or canceled)
            $subscription = Subscription::with('plan')
                ->where('company_id', $companyId)
                ->latest('id')
                ->first();
        }

        // 1. If No Subscription Record -> Fallback to Free Plan (Lifetime & Active)
        if (!$subscription) {
            $freePlan = Plan::where('slug', 'free')->first();
            return [
                'exists' => true,
                'is_free' => true,
                'is_active' => true,
                'is_expiring_soon' => false,
                'is_expired' => false,
                'days_remaining' => null,
                'subscription' => null,
                'plan' => $freePlan,
            ];
        }

        // 2. Check if Free Plan
        $isFree = ($subscription->price == 0 && $subscription->plan && ($subscription->plan->price_monthly == 0 || $subscription->plan->slug === 'free'));
        if ($isFree || is_null($subscription->end_date)) {
            return [
                'exists' => true,
                'is_free' => true,
                'is_active' => true,
                'is_expiring_soon' => false,
                'is_expired' => false,
                'days_remaining' => null,
                'subscription' => $subscription,
                'plan' => $subscription->plan,
            ];
        }

        // 3. Paid Subscription Time Calculations
        $endDate = Carbon::parse($subscription->end_date, $tz);
        $isYearly = ($subscription->billing_cycle === 'yearly');

        // Rules:
        // - Yearly: warning threshold 30 days (1 month)
        // - Monthly & Others: warning threshold 7 days (1 week)
        $warningThresholdDays = $isYearly ? 30 : 7;

        $daysRemaining = (int) $now->diffInDays($endDate, false);
        $isExpiringSoon = false;
        $isExpired = false;

        if ($now->greaterThan($endDate) || $subscription->status === 'expired') {
            // Subscription has reached or passed end_date -> EXPIRED & SUSPENDED IMMEDIATELY
            $isExpired = true;
            $isExpiringSoon = false;

            // Sync database status to expired in real-time if not already marked
            if ($subscription->status === 'active') {
                $subscription->update(['status' => 'expired']);
            }

            // Immediately revoke all personal access tokens for this company's users
            self::revokeCompanyUserTokens($companyId);
        } else {
            // Subscription is still active
            if ($daysRemaining <= $warningThresholdDays) {
                $isExpiringSoon = true;
            }
        }

        return [
            'exists' => true,
            'is_free' => false,
            'is_active' => !$isExpired,
            'is_expiring_soon' => $isExpiringSoon,
            'is_expired' => $isExpired,
            'days_remaining' => $daysRemaining,
            'threshold_days' => $warningThresholdDays,
            'subscription' => $subscription,
            'plan' => $subscription->plan,
        ];
    }

    /**
     * Check subscriptions for a company, perform synchronization, and send notifications.
     */
    public static function checkAndNotifyCompany(Company $company, bool $force = false): array
    {
        $status = self::getCompanySubscriptionStatus($company->id);

        if (!$status['exists'] || $status['is_free'] || !$status['subscription']) {
            return [
                'company' => $company->company_name,
                'status' => 'free_or_no_sub',
                'action_taken' => 'none',
            ];
        }

        $subscription = $status['subscription'];
        $companyAdmins = User::where('company_id', $company->id)
            ->where('role', 'company_admin')
            ->where('is_active', 1)
            ->get();

        if ($companyAdmins->isEmpty()) {
            return [
                'company' => $company->company_name,
                'status' => $status['is_expired'] ? 'expired' : 'active',
                'action_taken' => 'no_admins_found',
            ];
        }

        $actionTaken = 'checked';

        // 1. If Expired -> Send Immediate Expired & Service Cutoff Notification
        if ($status['is_expired']) {
            $alreadyNotifiedExpired = false;

            if (!$force) {
                $alreadyNotifiedExpired = DB::table('notifications')
                    ->whereIn('notifiable_id', $companyAdmins->pluck('id'))
                    ->where('data', 'like', '%"type":"subscription_expired"%')
                    ->where('data', 'like', '%"subscription_id":' . $subscription->id . '%')
                    ->exists();
            }

            if (!$alreadyNotifiedExpired) {
                try {
                    Notification::sendNow(
                        $companyAdmins,
                        new SubscriptionExpiredNotification($subscription)
                    );
                    $actionTaken = 'expired_notification_sent';
                } catch (Exception $e) {
                    Log::error("Failed to send subscription expired notification for company {$company->id}: " . $e->getMessage());
                }
            }
        }
        // 2. If Expiring Soon -> Send Expiring Soon Notification
        elseif ($status['is_expiring_soon']) {
            $alreadyNotifiedRecently = false;

            if (!$force) {
                // Check if notified in the last 48 hours for this subscription
                $twoDaysAgo = now()->subHours(48);
                $alreadyNotifiedRecently = DB::table('notifications')
                    ->whereIn('notifiable_id', $companyAdmins->pluck('id'))
                    ->where('data', 'like', '%"type":"subscription_expiring_soon"%')
                    ->where('data', 'like', '%"subscription_id":' . $subscription->id . '%')
                    ->where('created_at', '>=', $twoDaysAgo)
                    ->exists();
            }

            if (!$alreadyNotifiedRecently) {
                try {
                    Notification::sendNow(
                        $companyAdmins,
                        new SubscriptionExpiringSoonNotification(
                            $subscription,
                            $status['days_remaining']
                        )
                    );
                    $actionTaken = 'expiring_soon_notification_sent';
                } catch (Exception $e) {
                    Log::error("Failed to send subscription expiring soon notification for company {$company->id}: " . $e->getMessage());
                }
            }
        }

        return [
            'company' => $company->company_name,
            'is_expired' => $status['is_expired'],
            'days_remaining' => $status['days_remaining'],
            'action_taken' => $actionTaken,
        ];
    }

    /**
     * Revoke all personal access tokens for all users belonging to a company.
     */
    public static function revokeCompanyUserTokens(string $companyId): int
    {
        $userIds = User::where('company_id', $companyId)->pluck('id');
        if ($userIds->isEmpty()) {
            return 0;
        }

        return DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->delete();
    }

    /**
     * Get complete subscription response payload for API.
     */
    public static function getSubscriptionPayload(string $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company) {
            return [];
        }

        $status = self::getCompanySubscriptionStatus($companyId);
        $totalEmployees = Employee::where('company_id', $companyId)->count();

        $pendingRequest = SubscriptionUpgradeRequest::where('company_id', $companyId)
            ->where('status', 'pending')
            ->with(['requestedPlan'])
            ->first();

        $tz = $company->timezone ?: config('app.timezone', 'Asia/Riyadh');
        $subscription = $status['subscription'];
        $plan = $status['plan'];

        $maxUsers = $plan ? $plan->max_users : 5;
        $isUnlimited = ($maxUsers >= 999999 || $maxUsers === 0);
        $usagePercent = (!$isUnlimited && $maxUsers > 0) ? round(($totalEmployees / $maxUsers) * 100, 1) : 0;

        return [
            'subscription_id' => $subscription ? $subscription->id : null,
            'plan_id' => $plan ? $plan->id : null,
            'plan_name' => $plan ? $plan->name : 'الباقة المجانية',
            'plan_slug' => $plan ? $plan->slug : 'free',
            'billing_cycle' => $subscription ? $subscription->billing_cycle : 'free',
            'price' => $subscription ? $subscription->price : 0,
            'currency' => $subscription ? $subscription->currency : 'USD',
            'start_date' => ($subscription && $subscription->start_date) ? Carbon::parse($subscription->start_date, $tz)->toIso8601String() : null,
            'end_date' => ($subscription && $subscription->end_date) ? Carbon::parse($subscription->end_date, $tz)->toIso8601String() : null,
            'created_at' => ($subscription && $subscription->created_at) ? Carbon::parse($subscription->created_at, $tz)->toIso8601String() : null,
            'status' => $status['is_expired'] ? 'expired' : ($subscription ? $subscription->status : 'active'),

            'is_free' => $status['is_free'],
            'days_remaining' => $status['days_remaining'],
            'is_expiring_soon' => $status['is_expiring_soon'],
            'is_expired' => $status['is_expired'],
            'threshold_days' => $status['threshold_days'] ?? 7,

            'total_employees' => $totalEmployees,
            'max_users' => $maxUsers,
            'is_unlimited' => $isUnlimited,
            'usage_percent' => $usagePercent,
            'remaining_slots' => $isUnlimited ? 999999 : max(0, $maxUsers - $totalEmployees),
            'has_pending_upgrade_request' => $pendingRequest ? true : false,
            'pending_upgrade_request' => $pendingRequest,
        ];
    }
}
