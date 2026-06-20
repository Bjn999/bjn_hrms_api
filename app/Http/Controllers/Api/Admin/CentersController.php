<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Center;
use App\Models\Governorate;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CentersController extends Controller
{
    public function index(Request $request)
    {
        $com_code = $request->user()->com_code;
        $data = Center::with(['country:id,name', 'governorate:id,name', 'added:id,name', 'updatedby:id,name'])
            ->where('com_code', $com_code)
            ->orderBy('id', 'DESC')
            ->get();
        foreach ($data as $info) {
            $info->counterUsed = Employee::where(['com_code' => $com_code, 'city_id' => $info->id])->count();
        }
        return response()->json(['status' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $com_code = $request->user()->com_code;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'country_id' => 'required|exists:countries,id',
            'governorate_id' => 'required|exists:governorates,id',
            'active' => 'required|in:0,1'
        ]);

        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Center::where(['com_code' => $com_code, 'name' => $request->name, 'governorate_id' => $request->governorate_id])->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذا المركز مسجل من قبل'], 422);

        try {
            DB::beginTransaction();
            Center::create([
                'name' => $request->name,
                'country_id' => $request->country_id,
                'governorate_id' => $request->governorate_id,
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
            'governorate_id' => 'required|exists:governorates,id',
            'active' => 'required|in:0,1'
        ]);

        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Center::where(['com_code' => $com_code, 'name' => $request->name, 'governorate_id' => $request->governorate_id])->where('id', '!=', $id)->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذا المركز مسجل من قبل'], 422);

        try {
            DB::beginTransaction();
            $center = Center::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$center) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            $center->update([
                'name' => $request->name,
                'country_id' => $request->country_id,
                'governorate_id' => $request->governorate_id,
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
        $center = Center::where(['com_code' => $com_code, 'id' => $id])->first();
        if (!$center) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
        $counterUsed = Employee::where(['com_code' => $com_code, 'city_id' => $id])->count();
        if ($counterUsed > 0) return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف هذا المركز لوجود موظفين مرتبطين به'], 422);
        $center->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
    }

    public function getGovernorates(Request $request, $country_id)
    {
        $com_code = $request->user()->com_code;
        $data = Governorate::where(['com_code' => $com_code, 'country_id' => $country_id, 'active' => 1])->select('id', 'name')->get();
        return response()->json(['status' => true, 'data' => $data]);
    }
}
