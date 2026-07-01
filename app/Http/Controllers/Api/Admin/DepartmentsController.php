<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DepartmentsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $data = Department::with(['added:id,name', 'updatedby:id,name'])
                ->where("com_code", $user->com_code)
                ->orderBy('id', 'DESC')
                ->get();
            
            foreach ($data as $item) {
                $item->counterUsed = Employee::where(['com_code' => $user->com_code, 'emp_departments_id' => $item->id])->count();
            }
            
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
                'name' => 'required|string|unique:departments,name,NULL,id,com_code,'.$user->com_code,
                'phones' => 'required|string',
                'active' => 'required|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            Department::create([
                'name' => $request->name,
                'phones' => $request->phones,
                'active' => $request->active,
                'added_by' => $user->id,
                'com_code' => $user->com_code,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            return response()->json(['status' => true, 'message' => 'تم إضافة الإدارة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Department::where(['id' => $id, 'com_code' => $user->com_code])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'الإدارة غير موجودة'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', Rule::unique('departments')->where('com_code', $user->com_code)->ignore($id)],
                'phones' => 'required|string',
                'active' => 'required|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $counterUsed = Employee::where(['com_code' => $user->com_code, 'department_id' => $id])->count();
            if ($counterUsed > 0 && $request->active == 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن تعطيل الإدارة لوجود موظفين مرتبطين بها'], 422);
            }

            $data->update([
                'name' => $request->name,
                'phones' => $request->phones,
                'active' => $request->active,
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return response()->json(['status' => true, 'message' => 'تم تحديث الإدارة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Department::where(['id' => $id, 'com_code' => $user->com_code])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'الإدارة غير موجودة'], 404);
            }

            $counterUsed = Employee::where(['com_code' => $user->com_code, 'department_id' => $id])->count();
            if ($counterUsed > 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن حذف الإدارة لوجود موظفين مرتبطين بها'], 422);
            }

            $data->delete();

            return response()->json(['status' => true, 'message' => 'تم حذف الإدارة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
