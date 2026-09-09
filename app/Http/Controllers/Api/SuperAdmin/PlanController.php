<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    private function checkSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['status' => false, 'message' => __('messages.unauthorized')], 403));
        }
    }

    public function index()
    {
        $this->checkSuperAdmin();

        $plans = Plan::with(['planFeatures.feature', 'features'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $features = Feature::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'plans' => $plans,
                'available_features' => $features
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->checkSuperAdmin();

        $request->validate([
            'name' => 'required|string|max:190',
            'slug' => 'nullable|string|max:100|unique:plans,slug',
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_users' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'nullable|integer',
            'features' => 'nullable|array',
            'features.*.feature_id' => 'required|exists:features,id',
            'features.*.value' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $slug = $request->slug ? Str::slug($request->slug) : Str::slug($request->name);
            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (Plan::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            $plan = Plan::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'max_users' => $request->max_users,
                'is_active' => $request->boolean('is_active', true),
                'is_popular' => $request->boolean('is_popular', false),
                'sort_order' => $request->sort_order ?? 0,
            ]);

            if ($request->has('features') && is_array($request->features)) {
                foreach ($request->features as $f) {
                    PlanFeature::create([
                        'plan_id' => $plan->id,
                        'feature_id' => $f['feature_id'],
                        'value' => $f['value'] ?? '',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.plan_created'),
                'data' => $plan->load('features')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function show($id)
    {
        $this->checkSuperAdmin();

        $plan = Plan::with(['planFeatures.feature', 'features'])->find($id);

        if (!$plan) {
            return response()->json(['status' => false, 'message' => __('messages.plan_not_found')], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $plan
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->checkSuperAdmin();

        $plan = Plan::find($id);
        if (!$plan) {
            return response()->json(['status' => false, 'message' => __('messages.plan_not_found')], 404);
        }

        $request->validate([
            'name' => 'required|string|max:190',
            'slug' => 'nullable|string|max:100|unique:plans,slug,' . $id,
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_users' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            DB::beginTransaction();

            $slug = $request->slug ? Str::slug($request->slug) : $plan->slug;

            $plan->update([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'max_users' => $request->max_users,
                'is_active' => $request->boolean('is_active', true),
                'is_popular' => $request->boolean('is_popular', false),
                'sort_order' => $request->sort_order ?? $plan->sort_order,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.plan_updated'),
                'data' => $plan->fresh(['features'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function updatePlanFeatures(Request $request, $id)
    {
        $this->checkSuperAdmin();

        $plan = Plan::find($id);
        if (!$plan) {
            return response()->json(['status' => false, 'message' => __('messages.plan_not_found')], 404);
        }

        $request->validate([
            'features' => 'required|array',
            'features.*.feature_id' => 'required|exists:features,id',
            'features.*.value' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->features as $item) {
                PlanFeature::updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'feature_id' => $item['feature_id'],
                    ],
                    [
                        'value' => $item['value'] ?? '',
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.plan_features_updated'),
                'data' => $plan->fresh(['planFeatures.feature', 'features'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function destroy($id)
    {
        $this->checkSuperAdmin();

        $plan = Plan::find($id);
        if (!$plan) {
            return response()->json(['status' => false, 'message' => __('messages.plan_not_found')], 404);
        }

        // Check if plan has active subscriptions
        $hasActiveSubs = Subscription::where('plan_id', $id)->where('status', 'active')->exists();
        if ($hasActiveSubs) {
            return response()->json([
                'status' => false,
                'message' => __('messages.cannot_delete_plan_with_subscriptions')
            ], 422);
        }

        try {
            $plan->delete();
            return response()->json([
                'status' => true,
                'message' => __('messages.plan_deleted')
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }
}
