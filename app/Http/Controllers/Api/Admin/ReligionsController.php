<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Religion;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReligionsController extends Controller
{
    public function index(Request $request)
    {
        $com_code = $request->user()->com_code;
        $data = Religion::with(['added:id,name', 'updatedby:id,name'])
            ->where('com_code', $com_code)
            ->orderBy('id', 'DESC')
            ->get();
        foreach ($data as $info) {
            $info->counterUsed = Employee::where(['com_code' => $com_code, 'religion_id' => $info->id])->count();
        }
        return response()->json(['status' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $com_code = $request->user()->com_code;
        $validator = Validator::make($request->all(), ['name' => 'required', 'active' => 'required|in:0,1']);
        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Religion::where(['com_code' => $com_code, 'name' => $request->name])->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذه الديانة مسجلة من قبل'], 422);

        try {
            DB::beginTransaction();
            Religion::create([
                'name' => $request->name, 
                'active' => $request->active, 
                'com_code' => $com_code, 
                'added_by' => $request->user()->id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم إضافة البيانات بنجاح']);
        } catch (\Exception $ex) { DB::rollBack(); return response()->json(['status' => false, 'message' => 'error: ' . $ex->getMessage()], 500); }
    }

    public function update(Request $request, $id)
    {
        $com_code = $request->user()->com_code;
        $validator = Validator::make($request->all(), ['name' => 'required', 'active' => 'required|in:0,1']);
        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Religion::where(['com_code' => $com_code, 'name' => $request->name])->where('id', '!=', $id)->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذه الديانة مسجلة من قبل'], 422);

        try {
            DB::beginTransaction();
            $rel = Religion::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$rel) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            $rel->update([
                'name' => $request->name, 
                'active' => $request->active, 
                'updated_by' => $request->user()->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم تحديث البيانات بنجاح']);
        } catch (\Exception $ex) { DB::rollBack(); return response()->json(['status' => false, 'message' => 'error: ' . $ex->getMessage()], 500); }
    }

    public function destroy(Request $request, $id)
    {
        $com_code = $request->user()->com_code;
        $rel = Religion::where(['com_code' => $com_code, 'id' => $id])->first();
        if (!$rel) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
        $counterUsed = Employee::where(['com_code' => $com_code, 'religion_id' => $id])->count();
        if ($counterUsed > 0) return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف هذه الديانة لوجود موظفين مرتبطين بها'], 422);
        $rel->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
    }
}
