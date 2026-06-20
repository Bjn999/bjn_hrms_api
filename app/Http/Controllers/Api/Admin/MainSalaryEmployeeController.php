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
use Illuminate\Support\Facades\Validator;

class MainSalaryEmployeeController extends Controller
{
    use GeneralTrait;

    // List finance months (same logic as legacy index)
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

    // Show salary records for a specific finance month
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $finance_month_data = Finance_months_periods::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$finance_month_data) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي غير موجود'], 404);
            }

            $query = Main_salary_employee::where(['com_code' => $com_code, 'finance_month_id' => $id])
                ->with([
                    'added:id,name',
                    'updatedby:id,name',
                ]);

            // Filters
            // if ($request->employee_code && $request->employee_code !== 'all') {
            //     $query->where('employee_code', $request->employee_code);
            // }
            // if ($request->branch_id && $request->branch_id !== 'all') {
            //     $query->where('branch_id', $request->branch_id);
            // }
            // if ($request->emp_departments_id && $request->emp_departments_id !== 'all') {
            //     $query->where('emp_departments_id', $request->emp_departments_id);
            // }
            // if (isset($request->is_archived) && $request->is_archived !== 'all') {
            //     $query->where('is_archived', $request->is_archived);
            // }
            // if (isset($request->is_stoped) && $request->is_stoped !== 'all') {
            //     $query->where('is_stoped', $request->is_stoped);
            // }

            $salary_data = $query->orderBy('id', 'DESC')->get();

            foreach ($salary_data as $item) {
                $emp = Employee::where(['com_code' => $com_code, 'employee_code' => $item->employee_code])
                    ->first(['emp_name', 'emp_sal', 'day_price']);
                $item->emp_name_display = $emp ? $emp->emp_name : 'موظف غير موجود';
            }

            // Get all active employees for the "add salary" dropdown (not already in this month)
            $allEmployees = Employee::where(['com_code' => $com_code, 'functional_status' => 1])
                ->orderBy('emp_name')
                ->get(['employee_code', 'emp_name', 'emp_sal', 'day_price']);

            $existingCodes = Main_salary_employee::where(['com_code' => $com_code, 'finance_month_id' => $id])
                ->pluck('employee_code');

            $availableEmployees = $allEmployees->whereNotIn('employee_code', $existingCodes)->values();
            $nothavesal = $availableEmployees->count();

            return response()->json([
                'status' => true,
                'finance_month' => $finance_month_data,
                'data' => $salary_data,
                'available_employees' => $availableEmployees,
                'nothavesal' => $nothavesal,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Add a new salary record for an employee in a finance month
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $validator = Validator::make($request->all(), [
                'finance_month_id' => 'required',
                'employee_code'    => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $finance_month_data = Finance_months_periods::where([
                'com_code' => $com_code, 'id' => $request->finance_month_id, 'is_open' => 1
            ])->first();
            if (!$finance_month_data) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي مغلق أو غير موجود'], 422);
            }

            $employee = Employee::where([
                'com_code' => $com_code, 'employee_code' => $request->employee_code
            ])->first();
            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], 404);
            }

            $checkExist = Main_salary_employee::where([
                'com_code' => $com_code,
                'finance_month_id' => $request->finance_month_id,
                'employee_code' => $request->employee_code,
            ])->count();
            if ($checkExist > 0) {
                return response()->json(['status' => false, 'message' => 'هذا الموظف لديه سجل راتب في هذا الشهر'], 422);
            }

            // Get last salary balance
            $lastSalary = Main_salary_employee::where([
                'com_code' => $com_code,
                'employee_code' => $employee->employee_code,
                'is_archived' => 1,
            ])->orderBy('id', 'DESC')->first(['final_the_net_after_close']);
            $lastBalance = $lastSalary ? $lastSalary->final_the_net_after_close : 0;

            DB::beginTransaction();

            $newSalary = Main_salary_employee::create([
                'finance_month_id'     => $request->finance_month_id,
                'employee_code'        => $employee->employee_code,
                'emp_name'             => $employee->emp_name,
                'branch_id'            => $employee->branch_id,
                'emp_departments_id'   => $employee->emp_departments_id,
                'emp_job_id'           => $employee->emp_job_id,
                'functional_status'    => $employee->functional_status,
                'emp_sal'              => $employee->emp_sal,
                'day_price'            => $employee->day_price,
                'sal_cash_or_visa'     => $employee->sal_cash_or_visa,
                'last_salary_remain_balance' => $lastBalance,
                'year_and_month'       => $finance_month_data->year_and_month,
                'finance_yr'           => $finance_month_data->finance_yr,
                'com_code'             => $com_code,
                'added_by'             => $user->id,
                'is_archived'          => 0,
                'is_stoped'            => 0,
            ]);

            if ($newSalary) {
                $this->recaculate_main_salary_employee($newSalary->id);
            }

            DB::commit();

            return response()->json(['status' => true, 'message' => 'تم إضافة راتب الموظف بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Delete a salary record (only if month open and not archived)
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $salary = Main_salary_employee::where(['com_code' => $com_code, 'id' => $id, 'is_archived' => 0])->first();
            if (!$salary) {
                return response()->json(['status' => false, 'message' => 'سجل الراتب غير موجود أو مؤرشف'], 404);
            }

            $finance_month = Finance_months_periods::where([
                'com_code' => $com_code, 'id' => $salary->finance_month_id, 'is_open' => 1
            ])->first();
            if (!$finance_month) {
                return response()->json(['status' => false, 'message' => 'لا يمكن الحذف - الشهر المالي مغلق'], 422);
            }

            // Check for permanent loan installments
            $hasPloans = Main_salary_p_loans_installment::where(['com_code' => $com_code, 'main_salary_employee_id' => $id])->exists();
            if ($hasPloans) {
                return response()->json(['status' => false, 'message' => 'لا يمكن الحذف - يوجد أقساط مرتبطة بهذا السجل'], 422);
            }

            $salary->delete();

            return response()->json(['status' => true, 'message' => 'تم حذف راتب الموظف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Archive (close) a salary record → archives all related sanctions, absences, etc.
    public function archiveSalary(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $salary = Main_salary_employee::where([
                'com_code' => $com_code, 'id' => $id, 'is_archived' => 0, 'is_stoped' => 0
            ])->first();
            if (!$salary) {
                return response()->json(['status' => false, 'message' => 'سجل الراتب غير موجود أو مؤرشف أو موقوف'], 404);
            }

            $finance_month = Finance_months_periods::where([
                'com_code' => $com_code, 'id' => $salary->finance_month_id, 'is_open' => 1
            ])->first();
            if (!$finance_month) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي مغلق'], 422);
            }

            // Recalculate before archiving
            $this->recaculate_main_salary_employee($id);
            $salary->refresh();

            DB::beginTransaction();

            $finalNetAfterClose = $salary->final_the_net < 0 ? $salary->final_the_net : 0;

            $archiveData = [
                'is_archived'   => 1,
                'archived_by'   => $user->id,
                'archived_date' => date('Y-m-d H:i:s'),
                'updated_by'    => $user->id,
                'final_the_net_after_close' => $finalNetAfterClose,
            ];

            $salary->update($archiveData);

            $subArchiveData = [
                'is_archived'  => 1,
                'archived_by'  => $user->id,
                'archived_at'  => date('Y-m-d H:i:s'),
                'updated_by'   => $user->id,
            ];

            // Archive all related records in sub-tables
            Main_salary_employee_sanction::where([
                'com_code' => $com_code, 'main_salary_employee_id' => $id,
                'finance_month_periods_id' => $salary->finance_month_id
            ])->update($subArchiveData);

            Main_salary_employee_absence::where([
                'com_code' => $com_code, 'main_salary_employee_id' => $id,
                'finance_month_periods_id' => $salary->finance_month_id
            ])->update($subArchiveData);

            Main_salary_employee_discount::where([
                'com_code' => $com_code, 'main_salary_employee_id' => $id,
                'finance_month_periods_id' => $salary->finance_month_id
            ])->update($subArchiveData);

            Main_salary_employee_loan::where([
                'com_code' => $com_code, 'main_salary_employee_id' => $id,
                'finance_month_periods_id' => $salary->finance_month_id
            ])->update($subArchiveData);

            Main_salary_employee_addition::where([
                'com_code' => $com_code, 'main_salary_employee_id' => $id,
                'finance_month_periods_id' => $salary->finance_month_id
            ])->update($subArchiveData);

            Main_salary_employee_reward::where([
                'com_code' => $com_code, 'main_salary_employee_id' => $id,
                'finance_month_periods_id' => $salary->finance_month_id
            ])->update($subArchiveData);

            Main_salary_employee_allowance::where([
                'com_code' => $com_code, 'main_salary_employee_id' => $id,
                'finance_month_periods_id' => $salary->finance_month_id
            ])->update($subArchiveData);

            // Archive permanent loan installments
            Main_salary_p_loans_installment::where([
                'employee_code' => $salary->employee_code,
                'com_code' => $com_code,
                'year_and_month' => $finance_month->year_and_month,
                'is_archived' => 0,
                'main_salary_employee_id' => $id,
            ])->where('status', '!=', 2)->update($subArchiveData);

            DB::commit();

            return response()->json(['status' => true, 'message' => 'تم أرشفة راتب الموظف بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Stop a salary record
    public function stopSalary(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $salary = Main_salary_employee::where(['com_code' => $com_code, 'id' => $id, 'is_archived' => 0])->first();
            if (!$salary) {
                return response()->json(['status' => false, 'message' => 'سجل الراتب غير موجود أو مؤرشف'], 404);
            }
            if ($salary->is_stoped == 1) {
                return response()->json(['status' => false, 'message' => 'الراتب موقوف بالفعل'], 422);
            }

            Finance_months_periods::where(['com_code' => $com_code, 'id' => $salary->finance_month_id, 'is_open' => 1])
                ->firstOrFail();

            $salary->update(['is_stoped' => 1, 'updated_by' => $user->id]);

            return response()->json(['status' => true, 'message' => 'تم إيقاف راتب الموظف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Resume a stopped salary record
    public function resumeSalary(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $salary = Main_salary_employee::where(['com_code' => $com_code, 'id' => $id, 'is_archived' => 0])->first();
            if (!$salary) {
                return response()->json(['status' => false, 'message' => 'سجل الراتب غير موجود أو مؤرشف'], 404);
            }
            if ($salary->is_stoped == 0) {
                return response()->json(['status' => false, 'message' => 'الراتب مفعّل بالفعل'], 422);
            }

            Finance_months_periods::where(['com_code' => $com_code, 'id' => $salary->finance_month_id, 'is_open' => 1])
                ->firstOrFail();

            $salary->update(['is_stoped' => 0, 'updated_by' => $user->id]);

            return response()->json(['status' => true, 'message' => 'تم تفعيل راتب الموظف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Get a single salary record details (recalculate if not archived)
    public function salaryDetails(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $salary = Main_salary_employee::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$salary) {
                return response()->json(['status' => false, 'message' => 'سجل الراتب غير موجود'], 404);
            }

            if ($salary->is_archived == 0) {
                $this->recaculate_main_salary_employee($id);
                $salary->refresh();
            }

            $emp = Employee::where(['com_code' => $com_code, 'employee_code' => $salary->employee_code])
                ->first(['emp_name']);
            $salary->emp_name_display = $emp ? $emp->emp_name : 'غير موجود';

            $finance_month = Finance_months_periods::find($salary->finance_month_id);

            return response()->json([
                'status' => true,
                'data' => $salary,
                'finance_month' => $finance_month,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }


}
