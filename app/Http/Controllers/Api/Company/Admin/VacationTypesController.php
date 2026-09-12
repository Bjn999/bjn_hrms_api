<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\VacationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VacationTypesController extends Controller
{
    public function index(Request $request)
    {
        $company_id = $request->user()->company_id;

        // Auto-seed default vacation types if this company has none yet
        if ($company_id && VacationType::where('company_id', $company_id)->count() === 0) {
            $defaults = [
                ['name' => 'إجازة سنوية اعتيادية', 'type_slug' => 'annual', 'is_paid' => 1, 'deduct_from_balance' => 1, 'max_days_per_year' => 0, 'requires_attachment' => 0],
                ['name' => 'إجازة مرضية', 'type_slug' => 'sick', 'is_paid' => 1, 'deduct_from_balance' => 0, 'max_days_per_year' => 30, 'requires_attachment' => 1],
                ['name' => 'إجازة عارضة / طارئة', 'type_slug' => 'casual', 'is_paid' => 1, 'deduct_from_balance' => 1, 'max_days_per_year' => 7, 'requires_attachment' => 0],
                ['name' => 'إجازة بدون راتب', 'type_slug' => 'unpaid', 'is_paid' => 0, 'deduct_from_balance' => 0, 'max_days_per_year' => 0, 'requires_attachment' => 0],
                ['name' => 'إجازة أداء فريضة الحج', 'type_slug' => 'hajj', 'is_paid' => 1, 'deduct_from_balance' => 0, 'max_days_per_year' => 15, 'requires_attachment' => 0],
                ['name' => 'إجازة وضع / أمومة', 'type_slug' => 'maternity', 'is_paid' => 1, 'deduct_from_balance' => 0, 'max_days_per_year' => 70, 'requires_attachment' => 1],
            ];
            foreach ($defaults as $item) {
                VacationType::create(array_merge($item, [
                    'company_id' => $company_id,
                    'active' => 1,
                    'added_by' => $request->user()->id
                ]));
            }
        }
        
        $query = VacationType::where('company_id', $company_id)->withCount('requests');
        
        if ($request->has('active_only') && $request->active_only == '1') {
            $query->where('active', 1);
        }

        $data = $query->orderBy('id', 'ASC')->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $company_id = $request->user()->company_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type_slug' => 'nullable|string|max:50',
            'is_paid' => 'required|in:0,1',
            'deduct_from_balance' => 'required|in:0,1',
            'max_days_per_year' => 'nullable|numeric|min:0',
            'requires_attachment' => 'required|in:0,1',
            'active' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $exists = VacationType::where('company_id', $company_id)->where('name', $request->name)->exists();
        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => __('messages.data_exists')
            ], 422);
        }

        try {
            DB::beginTransaction();

            $type = VacationType::create([
                'company_id' => $company_id,
                'name' => $request->name,
                'type_slug' => $request->type_slug ?? 'annual',
                'is_paid' => $request->is_paid,
                'deduct_from_balance' => $request->deduct_from_balance,
                'max_days_per_year' => $request->max_days_per_year ?? 0,
                'requires_attachment' => $request->requires_attachment,
                'active' => $request->active,
                'added_by' => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.vacation_type_created'),
                'data' => $type
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $company_id = $request->user()->company_id;

        $type = VacationType::where('company_id', $company_id)->where('id', $id)->first();
        if (!$type) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_type_not_found')
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type_slug' => 'nullable|string|max:50',
            'is_paid' => 'required|in:0,1',
            'deduct_from_balance' => 'required|in:0,1',
            'max_days_per_year' => 'nullable|numeric|min:0',
            'requires_attachment' => 'required|in:0,1',
            'active' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $exists = VacationType::where('company_id', $company_id)
            ->where('name', $request->name)
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => __('messages.data_exists')
            ], 422);
        }

        try {
            DB::beginTransaction();

            $type->update([
                'name' => $request->name,
                'type_slug' => $request->type_slug ?? $type->type_slug,
                'is_paid' => $request->is_paid,
                'deduct_from_balance' => $request->deduct_from_balance,
                'max_days_per_year' => $request->max_days_per_year ?? 0,
                'requires_attachment' => $request->requires_attachment,
                'active' => $request->active,
                'updated_by' => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.vacation_type_updated'),
                'data' => $type
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $company_id = $request->user()->company_id;

        $type = VacationType::where('company_id', $company_id)->where('id', $id)->first();
        if (!$type) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_type_not_found')
            ], 404);
        }

        if ($type->requests()->exists()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_type_used_cannot_delete')
            ], 422);
        }

        $type->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.vacation_type_deleted')
        ]);
    }
}
