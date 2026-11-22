<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceActionsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $query = AttendanceAction::with(['employee:id,employee_code,emp_name', 'financePeriod', 'attendance', 'addedByAdmin:id,name', 'updatedByAdmin:id,name'])
                ->where("company_id", $user->company_id);

            if ($request->has('employee_code')) {
                $query->where('employee_code', $request->employee_code);
            }

            if ($request->has('attendance_id')) {
                $query->where('attendance_id', $request->attendance_id);
            }

            $data = $query->orderBy('actionDateTime', 'DESC')->get();

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'finance_months_periods_id' => 'required|integer',
                'attendance_id' => [
                    'required',
                    'integer',
                    \Illuminate\Validation\Rule::exists('attendances', 'id')->where('company_id', $user->company_id),
                ],
                'employee_code' => 'required|integer',
                'actionDateTime' => 'required|date_format:Y-m-d H:i:s',
                'actionType' => 'required|integer',
                'actionMethod' => 'required|in:1,2',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $allowed = $request->only([
                'finance_months_periods_id',
                'attendance_id',
                'employee_code',
                'actionDateTime',
                'actionType',
                'actionMethod',
                'notes',
            ]);

            $action = AttendanceAction::create(array_merge($allowed, [
                'company_id' => $user->company_id,
                'added_by' => $user->id,
            ]));

            return response()->json([
                'status' => true,
                'message' => 'تم تسجيل حركة البصمة بنجاح',
                'data' => $action
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = AttendanceAction::with(['employee', 'financePeriod', 'attendance', 'addedByAdmin', 'updatedByAdmin'])
                ->where(['id' => $id, 'company_id' => $user->company_id])
                ->first();

            if (!$data) {
                return response()->json(['status' => false, 'message' => 'الحركة غير موجودة'], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $action = AttendanceAction::where(['id' => $id, 'company_id' => $user->company_id])->first();

            if (!$action) {
                return response()->json(['status' => false, 'message' => 'الحركة غير موجودة'], 404);
            }

            $validator = Validator::make($request->all(), [
                'actionDateTime' => 'nullable|date_format:Y-m-d H:i:s',
                'actionType' => 'nullable|integer',
                'actionMethod' => 'nullable|in:1,2',
                'it_is_active_with_parent' => 'nullable|integer',
                'is_processed' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $allowed = $request->only([
                'actionDateTime',
                'actionType',
                'actionMethod',
                'it_is_active_with_parent',
                'is_processed',
                'notes',
            ]);

            $action->update(array_merge($allowed, [
                'updated_by' => $user->id,
            ]));

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث الحركة بنجاح',
                'data' => $action
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $action = AttendanceAction::where(['id' => $id, 'company_id' => $user->company_id])->first();

            if (!$action) {
                return response()->json(['status' => false, 'message' => 'الحركة غير موجودة'], 404);
            }

            $action->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف الحركة بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
