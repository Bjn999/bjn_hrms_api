<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionUpgradeRequest;
use App\Models\User;
use App\Models\Employee;
use App\Notifications\SubscriptionUpgradeStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SubscriptionUpgradeRequestController extends Controller
{
    private function checkSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['status' => false, 'message' => __('messages.unauthorized')], 403));
        }
    }

    /**
     * List all subscription upgrade/renewal requests
     */
    public function index(Request $request)
    {
        $this->checkSuperAdmin();

        $query = SubscriptionUpgradeRequest::with([
            'company',
            'user:id,name,email,username',
            'currentPlan',
            'requestedPlan',
            'reviewer:id,name'
        ]);

        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        $stats = [
            'total' => SubscriptionUpgradeRequest::count(),
            'pending' => SubscriptionUpgradeRequest::where('status', 'pending')->count(),
            'approved' => SubscriptionUpgradeRequest::where('status', 'approved')->count(),
            'rejected' => SubscriptionUpgradeRequest::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => [
                'requests' => $requests,
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * Approve upgrade/renewal request and activate new subscription automatically
     */
    public function approve(Request $request, $id)
    {
        $this->checkSuperAdmin();

        $upgradeRequest = SubscriptionUpgradeRequest::find($id);
        if (!$upgradeRequest) {
            return response()->json(['status' => false, 'message' => __('messages.upgrade_request_not_found')], 404);
        }

        if ($upgradeRequest->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => __('messages.saved_successfully')
            ], 422);
        }

        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $companyId = $upgradeRequest->company_id;
            $requestedPlan = Plan::findOrFail($upgradeRequest->requested_plan_id);
            $cycle = $upgradeRequest->requested_billing_cycle;

            // Determine price and end date
            $price = 0;
            $startDate = now();
            $endDate = null;

            if ($cycle === 'yearly') {
                $price = $requestedPlan->price_yearly;
                $endDate = now()->addYear()->endOfDay();
            } elseif ($cycle === 'monthly') {
                $price = $requestedPlan->price_monthly;
                $endDate = now()->addMonth()->endOfDay();
            } else {
                $price = 0;
                $endDate = null;
            }

            // 1. Mark existing active subscriptions as expired
            Subscription::where('company_id', $companyId)
                ->where('status', 'active')
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

            // 2. Create the new approved subscription
            $subscriptionNote = ($upgradeRequest->request_type === 'renewal')
                ? 'تم التجديد تلقائياً بناءً على طلب التجديد رقم #' . $upgradeRequest->id
                : 'تمت الترقية تلقائياً بناءً على طلب الترقية رقم #' . $upgradeRequest->id;

            if ($request->admin_notes) {
                $subscriptionNote .= ' - ملاحظة: ' . $request->admin_notes;
            }

            $newSubscription = Subscription::create([
                'company_id' => $companyId,
                'plan_id' => $requestedPlan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'billing_cycle' => $cycle,
                'price' => $price,
                'currency' => 'USD',
                'status' => 'active',
                'notes' => $subscriptionNote,
                'created_by' => auth()->id(),
            ]);

            // 3. Update the Upgrade Request status to approved
            $upgradeRequest->update([
                'status' => 'approved',
                'admin_notes' => $request->admin_notes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            DB::commit();

            // Notify Company Admin in real-time
            try {
                $companyAdmins = User::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('role', 'company_admin')
                    ->get();
                if ($companyAdmins->isNotEmpty()) {
                    Notification::sendNow($companyAdmins, new SubscriptionUpgradeStatusNotification($upgradeRequest->load(['requestedPlan']), 'approved'));
                }
            } catch (\Throwable $e) {
                Log::error('Failed to notify company admin of upgrade approval: ' . $e->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => __('messages.upgrade_request_approved'),
                'data' => [
                    'request' => $upgradeRequest->load(['company', 'user', 'currentPlan', 'requestedPlan', 'reviewer']),
                    'subscription' => $newSubscription->load('plan'),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    /**
     * Reject an upgrade/renewal request
     */
    public function reject(Request $request, $id)
    {
        $this->checkSuperAdmin();

        $upgradeRequest = SubscriptionUpgradeRequest::find($id);
        if (!$upgradeRequest) {
            return response()->json(['status' => false, 'message' => __('messages.upgrade_request_not_found')], 404);
        }

        if ($upgradeRequest->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => __('messages.saved_successfully')
            ], 422);
        }

        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $upgradeRequest->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Notify Company Admin in real-time
        try {
            $companyAdmins = User::withoutGlobalScopes()
                ->where('company_id', $upgradeRequest->company_id)
                ->where('role', 'company_admin')
                ->get();
            if ($companyAdmins->isNotEmpty()) {
                Notification::sendNow($companyAdmins, new SubscriptionUpgradeStatusNotification($upgradeRequest->load(['requestedPlan']), 'rejected'));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to notify company admin of upgrade rejection: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.upgrade_request_rejected'),
            'data' => $upgradeRequest->load(['company', 'user', 'currentPlan', 'requestedPlan', 'reviewer'])
        ]);
    }

    /**
     * Get active companies list with their subscriptions for the Subscriptions tab
     */
    public function companySubscriptionsList(Request $request)
    {
        $this->checkSuperAdmin();

        $query = Company::with([
            'currentSubscription.plan',
            'subscriptions' => function ($q) {
                $q->with('plan')->orderBy('id', 'desc');
            }
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('plan_id') && $request->plan_id !== 'all') {
            $planId = $request->plan_id;
            $query->whereHas('currentSubscription', function ($q) use ($planId) {
                $q->where('plan_id', $planId)->where('status', 'active');
            });
        }

        $companies = $query->orderBy('created_at', 'desc')->get();

        // Attach employee count and user count
        $companiesData = $companies->map(function ($company) {
            $totalEmployees = Employee::where('company_id', $company->id)->count();
            $totalUsers = User::where('company_id', $company->id)->count();
            $currentSub = $company->currentSubscription ?: $company->subscriptions->first();
            $maxUsers = ($currentSub && $currentSub->plan) ? $currentSub->plan->max_users : 0;

            return [
                'id' => $company->id,
                'name' => $company->company_name,
                'email' => $company->email,
                'phone' => $company->phone_number,
                'is_active' => $company->is_active,
                'created_at' => $company->created_at ? $company->created_at->format('Y-m-d') : null,
                'total_employees' => $totalEmployees,
                'total_users' => $totalUsers,
                'max_users' => $maxUsers,
                'current_subscription' => $currentSub,
            ];
        });

        $plans = Plan::where('is_active', true)
            ->where(function($q) {
                $q->where('max_users', '<', 999999)->where('slug', '!=', 'custom');
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'companies' => $companiesData,
                'plans' => $plans,
            ]
        ]);
    }
}
