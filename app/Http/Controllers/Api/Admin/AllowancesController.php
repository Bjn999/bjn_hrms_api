<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allowance;
use App\Models\Employee_fixed_allowance;
use App\Models\Main_salary_employee_allowance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AllowancesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $com_code = auth()->user()->com_code;
            $data = Allowance::with(['added:id,name', 'updatedby:id,name'])
                ->where('com_code', $com_code)
                ->orderBy('id', 'DESC')
                ->get();

            foreach ($data as $info) {
                $counterUsedFixed = Employee_fixed_allowance::where(['com_code' => $com_code, 'allowance_id' => $info->id])->count();
                $counterUsedMonthly = Main_salary_employee_allowance::where(['com_code' => $com_code, 'allowances_id' => $info->id])->count();
                $info->counterUsed = $counterUsedFixed + $counterUsedMonthly;
            }
            
            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Allowance::where(['com_code' => $user->com_code, 'name' => $request->name])->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'هذه البيانات موجودة من قبل'], 422);
            }

            Allowance::create([
                'name' => $request->name,
                'active' => $request->active,
                'com_code' => $user->com_code,
                'added_by' => $user->id,
            ]);

            return response()->json(['status' => true, 'message' => 'تم الحفظ بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $data = Allowance::where(['id' => $id, 'com_code' => $user->com_code])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Allowance::where(['com_code' => $user->com_code, 'name' => $request->name])->where('id', '!=', $id)->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'هذه البيانات موجودة من قبل'], 422);
            }

            $data->update([
                'name' => $request->name,
                'active' => $request->active,
                'updated_by' => $user->id,
            ]);

            return response()->json(['status' => true, 'message' => 'تم التعديل بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $data = Allowance::where(['id' => $id, 'com_code' => $user->com_code])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $counterUsedFixed = Employee_fixed_allowance::where(['com_code' => $user->com_code, 'allowance_id' => $id])->count();
            $counterUsedMonthly = Main_salary_employee_allowance::where(['com_code' => $user->com_code, 'allowances_id' => $id])->count();
            
            if (($counterUsedFixed + $counterUsedMonthly) > 0) {
                return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف هذا البدل لأنه مستخدم في النظام'], 422);
            }

            $data->delete();
            return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
