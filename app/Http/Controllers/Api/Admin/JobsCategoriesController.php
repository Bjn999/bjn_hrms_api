<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jobs_categories;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class JobsCategoriesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $data = Jobs_categories::with(['added:id,name', 'updatedby:id,name'])
                ->where("company_id", $user->company_id)
                ->orderBy('id', 'DESC')
                ->get();
            
            foreach ($data as $item) {
                $item->counterUsed = Employee::where(['company_id' => $user->company_id, 'emp_job_id' => $item->id])->count();
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
                'name' => 'required|string|unique:jobs_categories,name,NULL,id,company_id,'.$user->company_id,
                'active' => 'required|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            Jobs_categories::create([
                'name' => $request->name,
                'active' => $request->active,
                'added_by' => $user->id,
                'company_id' => $user->company_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            return response()->json(['status' => true, 'message' => 'تم إضافة فئة الوظيفة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Jobs_categories::where(['id' => $id, 'company_id' => $user->company_id])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'الفئة غير موجودة'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', Rule::unique('jobs_categories')->where('company_id', $user->company_id)->ignore($id)],
                'active' => 'required|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $counterUsed = Employee::where(['company_id' => $user->company_id, 'emp_job_id' => $id])->count();
            if ($counterUsed > 0 && $request->active == 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن تعطيل الفئة لوجود موظفين مرتبطين بها'], 422);
            }

            $data->update([
                'name' => $request->name,
                'active' => $request->active,
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return response()->json(['status' => true, 'message' => 'تم تحديث الفئة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Jobs_categories::where(['id' => $id, 'company_id' => $user->company_id])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'الفئة غير موجودة'], 404);
            }

            $counterUsed = Employee::where(['company_id' => $user->company_id, 'emp_job_id' => $id])->count();
            if ($counterUsed > 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن حذف الفئة لوجود موظفين مرتبطين بها'], 422);
            }

            $data->delete();

            return response()->json(['status' => true, 'message' => 'تم حذف الفئة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
