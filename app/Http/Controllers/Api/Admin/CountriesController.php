<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CountriesController extends Controller
{
    public function index(Request $request)
    {
        $company_id = $request->user()->company_id;
        $data = Country::with(['added:id,name', 'updatedby:id,name'])
            ->where('company_id', $company_id)
            ->orderBy('id', 'DESC')
            ->get();
        foreach ($data as $info) {
            $info->counterUsed = Employee::where(['company_id' => $company_id, 'country_id' => $info->id])->count();
        }
        return response()->json(['status' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $company_id = $request->user()->company_id;
        $validator = Validator::make($request->all(), ['name' => 'required', 'active' => 'required|in:0,1']);
        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Country::where(['company_id' => $company_id, 'name' => $request->name])->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذه الدولة مسجلة من قبل'], 422);

        try {
            DB::beginTransaction();
            Country::create([
                'name' => $request->name, 
                'active' => $request->active, 
                'company_id' => $company_id, 
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
        $company_id = $request->user()->company_id;
        $validator = Validator::make($request->all(), ['name' => 'required', 'active' => 'required|in:0,1']);
        if ($validator->fails()) return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);

        $checkExist = Country::where(['company_id' => $company_id, 'name' => $request->name])->where('id', '!=', $id)->first();
        if ($checkExist) return response()->json(['status' => false, 'message' => 'عفواً هذه الدولة مسجلة من قبل'], 422);

        try {
            DB::beginTransaction();
            $country = Country::where(['company_id' => $company_id, 'id' => $id])->first();
            if (!$country) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            $country->update([
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
        $company_id = $request->user()->company_id;
        $country = Country::where(['company_id' => $company_id, 'id' => $id])->first();
        if (!$country) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
        $counterUsed = Employee::where(['company_id' => $company_id, 'country_id' => $id])->count();
        if ($counterUsed > 0) return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف هذه الدولة لوجود موظفين مرتبطين بها'], 422);
        $country->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
    }
}
