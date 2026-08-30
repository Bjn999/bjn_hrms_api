<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRawLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceRawLogsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $query = AttendanceRawLog::with(['employee:id,employee_code,emp_name', 'financePeriod', 'addedByAdmin:id,name'])
                ->where("company_id", $user->company_id);

            if ($request->has('employee_code')) {
                $query->where('employee_code', $request->employee_code);
            }

            if ($request->has('finance_months_periods_id')) {
                $query->where('finance_months_periods_id', $request->finance_months_periods_id);
            }

            $data = $query->orderBy('actionDateTime', 'ASC')->get();

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
                'employee_code' => 'required|integer',
                'actionDateTime' => 'required|date_format:Y-m-d H:i:s',
                'actionType' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            // Check if raw log already exists to avoid duplicates
            $exists = AttendanceRawLog::where([
                'company_id' => $user->company_id,
                'employee_code' => $request->employee_code,
                'actionDateTime' => $request->actionDateTime,
                'actionType' => $request->actionType
            ])->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'DUPLICATE_RECORD'
                ], 422);
            }

            $log = AttendanceRawLog::create(array_merge($request->all(), [
                'company_id' => $user->company_id,
                'added_by' => $user->id,
            ]));

            return response()->json([
                'status' => true,
                'message' => 'تم حفظ سجل البصمة الخام بنجاح',
                'data' => $log
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = AttendanceRawLog::with(['employee', 'financePeriod', 'addedByAdmin'])
                ->where(['id' => $id, 'company_id' => $user->company_id])
                ->first();

            if (!$data) {
                return response()->json(['status' => false, 'message' => 'السجل الخام غير موجود'], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $log = AttendanceRawLog::where(['id' => $id, 'company_id' => $user->company_id])->first();

            if (!$log) {
                return response()->json(['status' => false, 'message' => 'السجل غير موجود'], 404);
            }

            $log->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف البصمة الخام بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
