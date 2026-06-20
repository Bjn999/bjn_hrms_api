<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Finance_calenders;
use App\Models\Finance_months_periods;
use App\Models\Main_salary_employee;
use App\Models\Main_salary_employee_absence;
use App\Models\Main_salary_employee_addition;
use App\Models\Main_salary_employee_allowance;
use App\Models\Main_salary_employee_discount;
use App\Models\Main_salary_employee_loan;
use App\Models\Main_salary_employee_reward;
use App\Models\Main_salary_employee_sanction;
use App\Models\Main_salary_p_loans_installment;
use App\Traits\GeneralTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryRecordsController extends Controller
{
    use GeneralTrait;

    // List finance months for the salary records page
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $finance_years = Finance_calenders::where('com_code', $com_code)
                ->whereIn('is_open', [1, 2])
                ->orderBy('finance_yr', 'DESC')
                ->get(['finance_yr']);

            $default_year = $finance_years->first() ? $finance_years->first()->finance_yr : null;
            $filter_year = $request->finance_yr && $request->finance_yr !== 'all' ? $request->finance_yr : $default_year;

            $query = Finance_months_periods::where('com_code', $com_code);
            
            if ($filter_year) {
                $query->where('finance_yr', $filter_year);
            }

            // Paginate exactly 12 per page
            $data = $query->orderBy('finance_yr', 'DESC')
                ->orderBy('month_id', 'ASC')
                ->paginate(12);

            foreach ($data as $info) {
                $info->currentYear = Finance_calenders::where(['com_code' => $com_code, 'finance_yr' => $info->finance_yr])
                    ->first(['is_open']);
                $info->counterOpenMonth = Finance_months_periods::where(['com_code' => $com_code, 'is_open' => 1])->count();
                $info->counterPreviousMonthWaitingOpen = Finance_months_periods::where([
                    'com_code' => $com_code, 'is_open' => 0, 'finance_yr' => $info->finance_yr
                ])->where('month_id', '<', $info->month_id)->count();
            }

            return response()->json([
                'status' => true,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                ],
                'finance_years' => $finance_years,
                'active_year' => $filter_year,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Get pasma dates before opening a month
    public function getPasmaDates(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $data = Finance_months_periods::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$data) return response()->json(['status' => false, 'message' => 'الشهر المالي غير موجود'], 404);

            return response()->json([
                'status' => true,
                'start_date_for_pasma' => $data->start_date_for_pasma,
                'end_date_for_pasma' => $data->end_date_for_pasma,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Open the finance month and generate salary records for all active employees
    public function doOpenMonth(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $data = Finance_months_periods::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$data) return response()->json(['status' => false, 'message' => 'الشهر المالي غير موجود'], 404);

            $currentYear = Finance_calenders::where(['com_code' => $com_code, 'finance_yr' => $data->finance_yr])->first();
            if (!$currentYear) return response()->json(['status' => false, 'message' => 'السنة المالية غير موجودة'], 404);
            if ($currentYear->is_open != 1) return response()->json(['status' => false, 'message' => 'السنة المالية التابع لها هذا الشهر غير مفتوحة حالياً'], 422);

            if ($data->is_open == 1) return response()->json(['status' => false, 'message' => 'هذا الشهر بالفعل مفتوح حالياً'], 422);
            if ($data->is_open == 2) return response()->json(['status' => false, 'message' => 'هذا الشهر بالفعل مؤرشف من قبل'], 422);

            $counterOpenMonth = Finance_months_periods::where(['com_code' => $com_code, 'is_open' => 1])->count();
            if ($counterOpenMonth > 0) return response()->json(['status' => false, 'message' => 'لا يمكن فتح هذا الشهر لوجود شهر آخر مفتوح حالياً'], 422);

            $counterPreviousMonthWaitingOpen = Finance_months_periods::where([
                'com_code' => $com_code, 'is_open' => 0, 'finance_yr' => $data->finance_yr
            ])->where('month_id', '<', $data->month_id)->count();
            if ($counterPreviousMonthWaitingOpen > 0) return response()->json(['status' => false, 'message' => 'لا يمكن فتح هذا الشهر لوجود شهر آخر قبله يستحق الفتح أولاً'], 422);

            DB::beginTransaction();

            $data->update([
                'start_date_for_pasma' => $request->start_date_for_pasma,
                'end_date_for_pasma' => $request->end_date_for_pasma,
                'is_open' => 1,
                'updated_by' => $user->id
            ]);

            // Open Employees Salary Codes
            $all_active_employees = Employee::where(['com_code' => $com_code, 'functional_status' => 1])->get();
            foreach ($all_active_employees as $info) {
                $checkExist = Main_salary_employee::where(['finance_month_id' => $id, 'employee_code' => $info->employee_code, 'com_code' => $com_code])->count();
                if ($checkExist == 0) {
                    $lastsalaryData = Main_salary_employee::where(['com_code' => $com_code, 'employee_code' => $info->employee_code, 'is_archived' => 1])
                        ->orderBy('id', 'DESC')->first(['final_the_net_after_close']);
                    $last_balance = $lastsalaryData ? $lastsalaryData->final_the_net_after_close : 0;

                    $newSalary = Main_salary_employee::create([
                        'finance_month_id' => $id,
                        'employee_code' => $info->employee_code,
                        'com_code' => $com_code,
                        'emp_name' => $info->emp_name,
                        'is_sensitive_manager_data' => $info->is_sensitive_manager_data,
                        'branch_id' => $info->branch_id,
                        'emp_departments_id' => $info->emp_departments_id,
                        'emp_job_id' => $info->emp_job_id,
                        'functional_status' => $info->functional_status,
                        'emp_sal' => $info->emp_sal,
                        'day_price' => $info->day_price,
                        'last_salary_remain_balance' => $last_balance,
                        'year_and_month' => $data->year_and_month,
                        'finance_yr' => $data->finance_yr,
                        'sal_cash_or_visa' => $info->sal_cash_or_visa,
                        'added_by' => $user->id,
                        'is_archived' => 0,
                        'is_stoped' => 0,
                    ]);

                    $this->recaculate_main_salary_employee($newSalary->id);
                }
            }

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم فتح الشهر المالي وإنشاء سجلات الرواتب بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Close the finance month and archive all its salary records
    public function doCloseMonth(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $data = Finance_months_periods::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$data) return response()->json(['status' => false, 'message' => 'الشهر المالي غير موجود'], 404);

            $currentYear = Finance_calenders::where(['com_code' => $com_code, 'finance_yr' => $data->finance_yr])->first();
            if (!$currentYear) return response()->json(['status' => false, 'message' => 'السنة المالية غير موجودة'], 404);
            if ($currentYear->is_open != 1) return response()->json(['status' => false, 'message' => 'السنة المالية التابع لها هذا الشهر غير مفتوحة حالياً'], 422);

            if ($data->is_open == 0) return response()->json(['status' => false, 'message' => 'هذا الشهر بانتظار الفتح'], 422);
            if ($data->is_open == 2) return response()->json(['status' => false, 'message' => 'هذا الشهر بالفعل مؤرشف من قبل'], 422);

            $counterStop = Main_salary_employee::where(['com_code' => $com_code, 'finance_month_id' => $id, 'is_stoped' => 1])->count();
            if ($counterStop > 0) return response()->json(['status' => false, 'message' => 'لا يمكن إغلاق الشهر لوجود رواتب موقوفة'], 422);

            DB::beginTransaction();

            $data->update(['is_open' => 2, 'updated_by' => $user->id]);

            $allMainSalaryEmployees = Main_salary_employee::where(['com_code' => $com_code, 'finance_month_id' => $id])->get();

            foreach ($allMainSalaryEmployees as $info) {
                $finalNetAfterClose = $info->final_the_net < 0 ? $info->final_the_net : 0;

                $info->update([
                    'is_archived' => 1,
                    'archived_by' => $user->id,
                    'archived_date' => date('Y-m-d H:i:s'),
                    'updated_by' => $user->id,
                    'final_the_net_after_close' => $finalNetAfterClose
                ]);

                $subArchiveData = [
                    'is_archived' => 1,
                    'archived_by' => $user->id,
                    'archived_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $user->id,
                ];

                Main_salary_employee_sanction::where(['com_code' => $com_code, 'main_salary_employee_id' => $info->id, 'finance_month_periods_id' => $id])->update($subArchiveData);
                Main_salary_employee_absence::where(['com_code' => $com_code, 'main_salary_employee_id' => $info->id, 'finance_month_periods_id' => $id])->update($subArchiveData);
                Main_salary_employee_discount::where(['com_code' => $com_code, 'main_salary_employee_id' => $info->id, 'finance_month_periods_id' => $id])->update($subArchiveData);
                Main_salary_employee_loan::where(['com_code' => $com_code, 'main_salary_employee_id' => $info->id, 'finance_month_periods_id' => $id])->update($subArchiveData);
                Main_salary_employee_addition::where(['com_code' => $com_code, 'main_salary_employee_id' => $info->id, 'finance_month_periods_id' => $id])->update($subArchiveData);
                Main_salary_employee_reward::where(['com_code' => $com_code, 'main_salary_employee_id' => $info->id, 'finance_month_periods_id' => $id])->update($subArchiveData);
                Main_salary_employee_allowance::where(['com_code' => $com_code, 'main_salary_employee_id' => $info->id, 'finance_month_periods_id' => $id])->update($subArchiveData);

                Main_salary_p_loans_installment::where([
                    'employee_code' => $info->employee_code,
                    'com_code' => $com_code,
                    'year_and_month' => $data->year_and_month,
                    'is_archived' => 0,
                    'main_salary_employee_id' => $info->id,
                ])->where('status', '!=', 2)->update($subArchiveData);
            }

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم إغلاق الشهر المالي بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
