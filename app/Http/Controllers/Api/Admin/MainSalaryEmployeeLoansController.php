<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance_calenders;
use App\Models\Finance_months_periods;
use App\Models\Main_salary_employee;
use App\Models\Main_salary_employee_loan;
use App\Traits\GeneralTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MainSalaryEmployeeLoansController extends Controller
{
    use GeneralTrait;

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

            $data = $query->orderBy('finance_yr', 'DESC')
                ->orderBy('month_id', 'ASC')
                ->get();

            foreach ($data as $info) {
                $info->currentYear = Finance_calenders::where(['com_code' => $com_code, 'finance_yr' => $info->finance_yr])->first(['is_open']);
                $info->counterOpenMonth = Finance_months_periods::with('month')->where(['com_code' => $com_code, 'is_open' => 1])->count();
                $info->counterPreviousMonthWaitingOpen = Finance_months_periods::with('month')->where(['com_code' => $com_code, 'is_open' => 0, 'finance_yr' => $info->finance_yr])
                    ->where('month_id', '<', $info->month_id)->count();
            }

            return response()->json([
                'status' => true,
                'data' => $data,
                'finance_years' => $finance_years
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $finance_month_data = Finance_months_periods::with('month')->where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$finance_month_data) {
                return response()->json(['status' => false, 'message' => 'عفواً غير قادر على الوصول إلى البيانات المطلوبة'], 404);
            }

            // Get loans
            $query = Main_salary_employee_loan::with([
                'employee:employee_code,emp_name',
                'added:id,name',
                'updatedby:id,name'
            ])->where(['com_code' => $com_code, 'finance_month_periods_id' => $id]);

            $loans_data = $query->orderBy('id', 'DESC')->get();

            // Get eligible employees for dropdown
            $employees = Main_salary_employee::where(['com_code' => $com_code, 'finance_month_id' => $id])
                ->with('employee:employee_code,emp_name,emp_sal,day_price')
                ->get();

            return response()->json([
                'status' => true,
                'finance_month' => $finance_month_data,
                'loans' => $loans_data,
                'employees' => $employees
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $validator = Validator::make($request->all(), [
                'finance_month_period_id' => 'required',
                'employee_code' => 'required',
                'total' => 'required|numeric|min:0.1',
                'notes' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $financeMonth_data = Finance_months_periods::with('month')->where(['com_code' => $com_code, 'id' => $request->finance_month_period_id, 'is_open' => 1])->first();
            if (!$financeMonth_data) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي المحدد مغلق أو غير موجود'], 422);
            }

            $mainSalaryEmployee_data = Main_salary_employee::where([
                'com_code' => $com_code, 
                'finance_month_id' => $request->finance_month_period_id, 
                'employee_code' => $request->employee_code, 
                'is_archived' => 0
            ])->first();

            if (!$mainSalaryEmployee_data) {
                return response()->json(['status' => false, 'message' => 'الموظف ليس لديه سجل راتب مفتوح في هذا الشهر'], 422);
            }

            DB::beginTransaction();

            $loan = Main_salary_employee_loan::create([
                'main_salary_employee_id' => $mainSalaryEmployee_data->id,
                'finance_month_periods_id' => $request->finance_month_period_id,
                'employee_code' => $request->employee_code,
                'day_price' => $mainSalaryEmployee_data->day_price,
                'total' => $request->total,
                'notes' => $request->notes,
                'com_code' => $com_code,
                'added_by' => $user->id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($loan) {
                $this->recaculate_main_salary_employee($mainSalaryEmployee_data->id);
            }

            DB::commit();

            return response()->json(['status' => true, 'message' => 'تم إضافة السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ عند إضافة السلفة: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $validator = Validator::make($request->all(), [
                'finance_month_period_id' => 'required',
                'employee_code' => 'required',
                'total' => 'required|numeric|min:0.1',
                'notes' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $loan = Main_salary_employee_loan::where(['id' => $id, 'com_code' => $com_code, 'is_archived' => 0])->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة أو تمت أرشفتها مسبقاً'], 404);
            }

            $financeMonth_data = Finance_months_periods::with('month')->where(['com_code' => $com_code, 'id' => $request->finance_month_period_id, 'is_open' => 1])->first();
            if (!$financeMonth_data) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي المحدد مغلق أو غير موجود'], 422);
            }

            $mainSalaryEmployee_data = Main_salary_employee::where([
                'com_code' => $com_code, 
                'finance_month_id' => $request->finance_month_period_id, 
                'employee_code' => $request->employee_code, 
                'is_archived' => 0
            ])->first();

            if (!$mainSalaryEmployee_data) {
                return response()->json(['status' => false, 'message' => 'الموظف ليس لديه سجل راتب مفتوح في هذا الشهر'], 422);
            }

            DB::beginTransaction();
            $old_main_salary_employee_id = $loan->main_salary_employee_id;

            $loan->update([
                'main_salary_employee_id' => $mainSalaryEmployee_data->id,
                'employee_code' => $request->employee_code,
                'day_price' => $mainSalaryEmployee_data->day_price,
                'total' => $request->total,
                'notes' => $request->notes,
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($old_main_salary_employee_id != $mainSalaryEmployee_data->id) {
                $this->recaculate_main_salary_employee($old_main_salary_employee_id);
            }
            $this->recaculate_main_salary_employee($mainSalaryEmployee_data->id);

            DB::commit();

            return response()->json(['status' => true, 'message' => 'تم تعديل السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ عند تعديل السلفة: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $loan = Main_salary_employee_loan::where(['id' => $id, 'com_code' => $com_code, 'is_archived' => 0])->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة أو تمت أرشفتها مسبقاً'], 404);
            }

            $financeMonth_data = Finance_months_periods::with('month')->where(['com_code' => $com_code, 'id' => $loan->finance_month_periods_id, 'is_open' => 1])->first();
            if (!$financeMonth_data) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي مغلق، لا يمكن حذف السجل'], 422);
            }

            $mainSalaryEmployee_data = Main_salary_employee::where([
                'com_code' => $com_code, 
                'id' => $loan->main_salary_employee_id, 
                'is_archived' => 0
            ])->first();

            DB::beginTransaction();

            $loan->delete();

            if ($mainSalaryEmployee_data) {
                $this->recaculate_main_salary_employee($mainSalaryEmployee_data->id);
            }

            DB::commit();

            return response()->json(['status' => true, 'message' => 'تم حذف السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ عند حذف السلفة: ' . $e->getMessage()], 500);
        }
    }
}
