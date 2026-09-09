<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionUpgradeRequest;
use App\Models\User;
use App\Notifications\SubscriptionUpgradeRequestedNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SubscriptionUpgradeRequestController extends Controller
{
    /**
     * Get company's upgrade requests history & pending status
     */
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;
        if (!$companyId) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 403);
        }

        $requests = SubscriptionUpgradeRequest::where('company_id', $companyId)
            ->with(['currentPlan', 'requestedPlan'])
            ->orderBy('created_at', 'desc')
            ->get();

        $hasPending = SubscriptionUpgradeRequest::where('company_id', $companyId)
            ->where('status', 'pending')
            ->exists();

        return response()->json([
            'status' => true,
            'data' => [
                'requests' => $requests,
                'has_pending' => $hasPending,
            ]
        ]);
    }

    /**
     * Submit a new subscription upgrade or renewal request
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->company_id;

        if (!$companyId) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 403);
        }

        $request->validate([
            'requested_plan_id' => 'required|exists:plans,id',
            'requested_billing_cycle' => 'required|in:monthly,yearly,free',
            'request_type' => 'required|in:upgrade,renewal',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Reject renewal requests on Free Plans
        if ($request->request_type === 'renewal') {
            $requestedPlan = Plan::find($request->requested_plan_id);
            if ($requestedPlan && ($requestedPlan->price_monthly == 0 || $requestedPlan->slug === 'free')) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.free_plan_cannot_be_renewed'),
                ], 422);
            }
        }

        // Check if there is already a pending request
        $existingPending = SubscriptionUpgradeRequest::where('company_id', $companyId)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return response()->json([
                'status' => false,
                'message' => __('messages.upgrade_request_already_pending'),
            ], 422);
        }

        $company = Company::find($companyId);
        $currentSub = $company ? ($company->currentSubscription ?: $company->subscriptions()->first()) : null;
        $currentPlanId = $currentSub ? $currentSub->plan_id : null;

        $upgradeRequest = SubscriptionUpgradeRequest::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'current_plan_id' => $currentPlanId,
            'requested_plan_id' => $request->requested_plan_id,
            'requested_billing_cycle' => $request->requested_billing_cycle,
            'request_type' => $request->request_type,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        // Notify all Super Admins
        try {
            $superAdmins = User::withoutGlobalScopes()->where('role', 'super_admin')->get();
            if ($superAdmins->isNotEmpty()) {
                Notification::sendNow($superAdmins, new SubscriptionUpgradeRequestedNotification($upgradeRequest->load(['company', 'requestedPlan'])));
            }
        } catch (Exception $e) {
            Log::error('Failed to dispatch upgrade notification to super admins: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.upgrade_request_submitted'),
            'data' => $upgradeRequest->load(['currentPlan', 'requestedPlan']),
        ], 201);
    }
}
