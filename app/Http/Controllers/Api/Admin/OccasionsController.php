<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Occasion;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OccasionsController extends Controller
{
    public function index(Request $request)
    {
        $com_code = $request->user()->com_code;
        $data = Occasion::with(['added:id,name', 'updatedby:id,name'])
            ->where('com_code', $com_code)
            ->orderBy('id', 'DESC')
            ->get();
        
        foreach ($data as $item) {
            // Check if used (occasions usually not directly in employees, but let's assume for consistency if needed)
            // or just set counterUsed to 0 if no direct link exists yet.
            $item->counterUsed = 0; 
        }

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $com_code = $request->user()->com_code;
        
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'days_counter' => 'required|numeric',
            'active' => 'required|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $checkExist = Occasion::where(['com_code' => $com_code, 'name' => $request->name])->first();
        if ($checkExist) {
            return response()->json(['status' => false, 'message' => 'عفواً هذه المناسبة مسجلة من قبل'], 422);
        }

        try {
            DB::beginTransaction();
            $data = Occasion::create([
                'name' => $request->name,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'days_counter' => $request->days_counter,
                'active' => $request->active,
                'com_code' => $com_code,
                'added_by' => $request->user()->id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم إضافة البيانات بنجاح']);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $ex->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $com_code = $request->user()->com_code;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'days_counter' => 'required|numeric',
            'active' => 'required|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $checkExist = Occasion::where(['com_code' => $com_code, 'name' => $request->name])->where('id', '!=', $id)->first();
        if ($checkExist) {
            return response()->json(['status' => false, 'message' => 'عفواً هذه المناسبة مسجلة من قبل'], 422);
        }

        try {
            DB::beginTransaction();
            $occasion = Occasion::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$occasion) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);

            $occasion->update([
                'name' => $request->name,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'days_counter' => $request->days_counter,
                'active' => $request->active,
                'updated_by' => $request->user()->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم تحديث البيانات بنجاح']);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $ex->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $com_code = $request->user()->com_code;
        $occasion = Occasion::where(['com_code' => $com_code, 'id' => $id])->first();
        if (!$occasion) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);

        $occasion->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
    }
}
