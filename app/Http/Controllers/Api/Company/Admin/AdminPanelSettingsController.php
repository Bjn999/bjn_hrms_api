<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminPanelSettingsController extends Controller
{
    public function index(Request $request)
    {
        $company_id = $request->user()->company_id;
        $company = Company::with('settings')->where('id', $company_id)->first();
        
        if (!$company) {
            return response()->json(['status' => false, 'message' => 'Company not found'], 404);
        }

        // Merge company and settings data into one array to maintain compatibility with existing frontend
        $data = array_merge($company->toArray(), $company->settings ? $company->settings->toArray() : []);

        return response()->json([
            'status' => true,
            'data' => (object) $data
        ]);
    }

    public function update(Request $request)
    {
        $company_id = $request->user()->company_id;
        
        $validator = Validator::make($request->all(), [
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
            DB::beginTransaction();

            $settingDataToUpdate = $request->only([
                'after_miniute_calculate_delay', 'after_miniute_calculate_early_departure',
                'after_miniute_quarterday', 'after_time_half_daycut', 'after_time_allday_daycut',
                'monthly_vacation_balance', 'after_days_begin_vacation', 'first_balance_begin_vacation',
                'sanctions_value_first_abcence', 'sanctions_value_second_abcence',
                'sanctions_value_third_abcence', 'sanctions_value_fourth_abcence'
            ]);
            
            $settingDataToUpdate['updated_by'] = $request->user()->id;

            $settings = CompanySetting::where('company_id', $company_id)->first();
            if ($settings) {
                $settings->update($settingDataToUpdate);
            } else {
                $settingDataToUpdate['company_id'] = $company_id;
                $settingDataToUpdate['added_by'] = $request->user()->id;
                CompanySetting::create($settingDataToUpdate);
            }

            DB::commit();

            // Refetch merged data
            $updatedCompany = Company::with('settings')->where('id', $company_id)->first();
            $data = array_merge($updatedCompany->toArray(), $updatedCompany->settings ? $updatedCompany->settings->toArray() : []);

            return response()->json([
                'status' => true,
                'message' => 'تم تعديل البيانات بنجاح',
                'data' => (object) $data
            ]);

        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ: ' . $ex->getMessage()
            ], 500);
        }
    }

    public function updateCompanyProfile(Request $request)
    {
        $company_id = $request->user()->company_id;
        
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'phones' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'timezone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error'),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $company = Company::find($company_id);
            if ($company) {
                $company->update($request->only([
                    'company_name', 'phones', 'address', 'email', 'timezone'
                ]));
            }

            DB::commit();

            // Refetch merged data
            $updatedCompany = Company::with('settings')->where('id', $company_id)->first();
            $data = array_merge($updatedCompany->toArray(), $updatedCompany->settings ? $updatedCompany->settings->toArray() : []);

            return response()->json([
                'status' => true,
                'message' => __('messages.updated_successfully'),
                'data' => (object) $data
            ]);

        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $ex->getMessage()
            ], 500);
        }
    }

    public function getSubscriptionInfo(Request $request)
    {
        $company_id = $request->user()->company_id;
        $company = Company::find($company_id);

        if (!$company) {
            return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
        }

        $payload = SubscriptionService::getSubscriptionPayload($company_id);

        return response()->json([
            'status' => true,
            'data' => $payload,
        ]);
    }
}

