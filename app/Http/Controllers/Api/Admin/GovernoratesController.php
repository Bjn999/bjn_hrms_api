<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Governorate;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GovernoratesController extends Controller
{
    public function index(Request $request)
    {
        $com_code = $request->user()->com_code;
        $data = Governorate::with(['country:id,name', 'added:id,name', 'updatedby:id,name'])
            ->where('com_code', $com_code)
            ->orderBy('id', 'DESC')
            ->get();
        foreach ($data as $info) {
            $info->counterUsed = Employee::where(['com_code' => $com_code, 'governorate_id' => $info->id])->count();
        }
        return response()->json(['status' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $com_code = $request->user()->com_code;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'country_id' => 'required|exists:countries,id',
            'active' => 'required|in:0,1'
        ]);

        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Governorate::where(['com_code' => $com_code, 'name' => $request->name, 'country_id' => $request->country_id])->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذه المحافظة مسجلة من قبل'], 422);

        try {
            DB::beginTransaction();
            Governorate::create([
                'name' => $request->name,
                'country_id' => $request->country_id,
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
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'country_id' => 'required|exists:countries,id',
            'active' => 'required|in:0,1'
        ]);

        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Governorate::where(['com_code' => $com_code, 'name' => $request->name, 'country_id' => $request->country_id])->where('id', '!=', $id)->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذه المحافظة مسجلة من قبل'], 422);

        try {
            DB::beginTransaction();
            $gov = Governorate::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$gov) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            $gov->update([
                'name' => $request->name,
                'country_id' => $request->country_id,
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
        $gov = Governorate::where(['com_code' => $com_code, 'id' => $id])->first();
        if (!$gov) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
        $counterUsed = Employee::where(['com_code' => $com_code, 'governorate_id' => $id])->count();
        if ($counterUsed > 0) return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف هذه المحافظة لوجود موظفين مرتبطين بها'], 422);
        $gov->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
    }
}
