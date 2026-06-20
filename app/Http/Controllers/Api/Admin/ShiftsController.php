<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\shifts_type;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShiftsController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();
            $data = shifts_type::with(['added:id,name', 'updatedby:id,name'])
                ->where("com_code", $user->com_code)
                ->orderBy('id', 'DESC')
                ->get();
            
            foreach ($data as $item) {
                $item->counterUsed = Employee::where(['com_code' => $user->com_code, 'shift_type_id' => $item->id])->count();
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
                'type' => 'required',
                'from_time' => 'required',
                'to_time' => 'required',
                'total_hour' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = shifts_type::where([
                'com_code' => $user->com_code,
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour
            ])->first();

            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'هذه البيانات موجودة من قبل'], 422);
            }

            shifts_type::create([
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour,
                'active' => $request->active,
                'com_code' => $user->com_code,
                'added_by' => $user->id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
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
            $data = shifts_type::where(['id' => $id, 'com_code' => $user->com_code])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required',
                'from_time' => 'required',
                'to_time' => 'required',
                'total_hour' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = shifts_type::where([
                'com_code' => $user->com_code,
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour
            ])->where('id', '!=', $id)->first();

            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'هذه البيانات موجودة من قبل'], 422);
            }

            $counterUsed = Employee::where(['com_code' => $user->com_code, 'shift_type_id' => $id])->count();
            if ($counterUsed > 0 && $request->active == 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن تعطيل هذا السجل لارتباطه بموظفين'], 422);
            }

            $data->update([
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour,
                'active' => $request->active,
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
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
            $data = shifts_type::where(['id' => $id, 'com_code' => $user->com_code])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $counterUsed = Employee::where(['com_code' => $user->com_code, 'shift_type_id' => $id])->count();
            if ($counterUsed > 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن الحذف لارتباطه بموظفين'], 422);
            }

            $data->delete();
            return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
