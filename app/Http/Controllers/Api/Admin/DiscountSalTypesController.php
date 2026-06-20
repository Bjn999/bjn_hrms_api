<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discount_sal_type;
use App\Models\Main_salary_employee_discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscountSalTypesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $com_code = auth()->user()->com_code;
            $data = Discount_sal_type::with(['added:id,name', 'updatedby:id,name'])
                ->where('com_code', $com_code)
                ->orderBy('id', 'DESC')
                ->get();

            foreach ($data as $info) {
                $info->counterUsed = Main_salary_employee_discount::where(['com_code' => $com_code, 'discounts_type_id' => $info->id])->count();
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

            $checkExist = Discount_sal_type::where(['com_code' => $user->com_code, 'name' => $request->name])->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'هذه البيانات موجودة من قبل'], 422);
            }

            Discount_sal_type::create([
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
            $data = Discount_sal_type::where(['id' => $id, 'com_code' => $user->com_code])->first();
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

            $checkExist = Discount_sal_type::where(['com_code' => $user->com_code, 'name' => $request->name])->where('id', '!=', $id)->first();
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
            $data = Discount_sal_type::where(['id' => $id, 'com_code' => $user->com_code])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $counterUsed = Main_salary_employee_discount::where(['com_code' => $user->com_code, 'discounts_type_id' => $id])->count();
            if ($counterUsed > 0) {
                return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف هذا النوع لأنه مستخدم في النظام'], 422);
            }

            $data->delete();
            return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
