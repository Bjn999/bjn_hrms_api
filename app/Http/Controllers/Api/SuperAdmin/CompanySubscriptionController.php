<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Employee;
use App\Models\Branche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanySubscriptionController extends Controller
{
    private function checkSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['status' => false, 'message' => __('messages.unauthorized')], 403));
        }
    }

    public function show($companyId)
    {
        $this->checkSuperAdmin();

        $company = Company::with(['settings'])->find($companyId);
        if (!$company) {
            return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
        }

        // Active and total users
        $totalUsers = User::where('company_id', $companyId)->count();
        $activeUsers = User::where('company_id', $companyId)->where('is_active', 1)->count();
        $totalEmployees = Employee::where('company_id', $companyId)->count();
        $totalBranches = Branche::where('company_id', $companyId)->count();

        // Company admin user details
        $adminUser = User::where('company_id', $companyId)
            ->where('role', 'company_admin')
            ->select('id', 'name', 'username', 'email', 'created_at', 'is_active')
            ->first();

        // Current Active Subscription with Plan & Features
        $currentSubscription = Subscription::with(['plan.features', 'creator:id,name'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', date('Y-m-d'));
            })
            ->latest('id')
            ->first();

        // Subscription History Log
        $subscriptionHistory = Subscription::with(['plan:id,name,slug,max_users', 'creator:id,name'])
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        // Available plans for quick assignment
        $availablePlans = Plan::where('is_active', true)->orderBy('sort_order', 'asc')->get();

        return response()->json([
            'status' => true,
            'data' => [
                'company' => $company,
                'admin_user' => $adminUser,
                'stats' => [
                    'active_users' => $activeUsers,
                    'total_users' => $totalUsers,
                    'total_employees' => $totalEmployees,
                    'total_branches' => $totalBranches,
                    'max_users' => $currentSubscription ? $currentSubscription->plan->max_users : 0,
                    'usage_percent' => ($currentSubscription && $currentSubscription->plan->max_users > 0 && $currentSubscription->plan->max_users < 999999) 
                        ? round(($totalEmployees / $currentSubscription->plan->max_users) * 100, 1) 
                        : 0,

                ],
                'current_subscription' => $currentSubscription,
                'subscription_history' => $subscriptionHistory,
                'available_plans' => $availablePlans,
            ]
        ]);
    }

    public function storeSubscription(Request $request, $companyId)
    {
        $this->checkSuperAdmin();

        $company = Company::find($companyId);
        if (!$company) {
            return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'billing_cycle' => 'required|in:monthly,yearly,lifetime,free,custom',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'status' => 'required|in:active,expired,canceled,trial',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // If new status is active, optionally mark previous active subscriptions as expired
            if ($request->status === 'active') {
                Subscription::where('company_id', $companyId)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'expired',
                        'updated_at' => now()
                    ]);
            }

            $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now();
            $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : null;

            $subscription = Subscription::create([
                'company_id' => $companyId,
                'plan_id' => $request->plan_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'billing_cycle' => $request->billing_cycle,
                'price' => $request->price,
                'currency' => $request->currency,
                'status' => $request->status,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);


            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.subscription_created'),
                'data' => $subscription->load(['plan', 'creator:id,name'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function updateSubscriptionStatus(Request $request, $subscriptionId)
    {
        $this->checkSuperAdmin();

        $subscription = Subscription::find($subscriptionId);
        if (!$subscription) {
            return response()->json(['status' => false, 'message' => __('messages.subscription_not_found')], 404);
        }

        $request->validate([
            'status' => 'required|in:active,expired,canceled,trial',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $subscription->update([
                'status' => $request->status,
                'notes' => $request->notes ?? $subscription->notes,
            ]);

            return response()->json([
                'status' => true,
                'message' => __('messages.subscription_updated'),
                'data' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }
}
