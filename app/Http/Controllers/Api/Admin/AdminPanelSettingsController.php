<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin_panel_settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPanelSettingsController extends Controller
{
    public function index(Request $request)
    {
        $com_code = $request->user()->com_code;
        $data = Admin_panel_settings::where('com_code', $com_code)->first();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function update(Request $request)
    {
        $com_code = $request->user()->com_code;
        
        $validator = Validator::make($request->all(), [
            'company_name' => 'required',
            'phones' => 'required',
            'address' => 'required',
            'email' => 'required|email',
            'after_miniute_calculate_delay' => 'required|numeric',
            'after_miniute_calculate_early_departure' => 'required|numeric',
            'after_miniute_quarterday' => 'required|numeric',
            'after_time_half_daycut' => 'required|numeric',
            'after_time_allday_daycut' => 'required|numeric',
            'monthly_vacation_balance' => 'required|numeric',
            'after_days_begin_vacation' => 'required|numeric',
            'first_balance_begin_vacation' => 'required|numeric',
            'sanctions_value_first_abcence' => 'required|numeric',
            'sanctions_value_second_abcence' => 'required|numeric',
            'sanctions_value_third_abcence' => 'required|numeric',
            'sanctions_value_fourth_abcence' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'يوجد خطأ في البيانات المدخلة',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $dataToUpdate = $request->only([
                'company_name', 'phones', 'address', 'email',
                'after_miniute_calculate_delay', 'after_miniute_calculate_early_departure',
                'after_miniute_quarterday', 'after_time_half_daycut', 'after_time_allday_daycut',
                'monthly_vacation_balance', 'after_days_begin_vacation', 'first_balance_begin_vacation',
                'sanctions_value_first_abcence', 'sanctions_value_second_abcence',
                'sanctions_value_third_abcence', 'sanctions_value_fourth_abcence'
            ]);

            // Note: Image upload can be handled here if $request->hasFile('image')
            
            $dataToUpdate['updated_by'] = $request->user()->id;

            Admin_panel_settings::where('com_code', $com_code)->update($dataToUpdate);

            $data = Admin_panel_settings::where('com_code', $com_code)->first();

            return response()->json([
                'status' => true,
                'message' => 'تم تعديل البيانات بنجاح',
                'data' => $data
            ]);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ: ' . $ex->getMessage()
            ], 500);
        }
    }
}
