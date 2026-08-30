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
            $company_id = auth()->user()->company_id;
            $data = Allowance::with(['added:id,name', 'updatedby:id,name'])
                ->where('company_id', $company_id)
                ->orderBy('id', 'DESC')
                ->get();

            foreach ($data as $info) {
                $counterUsedFixed = Employee_fixed_allowance::where(['company_id' => $company_id, 'allowance_id' => $info->id])->count();
                $counterUsedMonthly = Main_salary_employee_allowance::where(['company_id' => $company_id, 'allowances_id' => $info->id])->count();
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

            $checkExist = Allowance::where(['company_id' => $user->company_id, 'name' => $request->name])->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'هذه البيانات موجودة من قبل'], 422);
            }

            Allowance::create([
                'name' => $request->name,
                'active' => $request->active,
                'company_id' => $user->company_id,
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
            $data = Allowance::where(['id' => $id, 'company_id' => $user->company_id])->first();
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

            $checkExist = Allowance::where(['company_id' => $user->company_id, 'name' => $request->name])->where('id', '!=', $id)->first();
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
            $data = Allowance::where(['id' => $id, 'company_id' => $user->company_id])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $counterUsedFixed = Employee_fixed_allowance::where(['company_id' => $user->company_id, 'allowance_id' => $id])->count();
            $counterUsedMonthly = Main_salary_employee_allowance::where(['company_id' => $user->company_id, 'allowances_id' => $id])->count();
            
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
