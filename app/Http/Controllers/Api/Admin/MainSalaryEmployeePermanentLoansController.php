<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Main_salary_employee_permanent_loan;
use App\Models\Main_salary_p_loans_installment;
use App\Models\Finance_calenders;
use App\Traits\GeneralTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MainSalaryEmployeePermanentLoansController extends Controller
{
    use GeneralTrait;

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $query = Main_salary_employee_permanent_loan::where('com_code', $com_code);

            if ($request->has('employee_code') && $request->employee_code !== 'all') {
                $query->where('employee_code', $request->employee_code);
            }
            if ($request->has('is_dismissal') && $request->is_dismissal !== 'all') {
                $query->where('is_dismissal', $request->is_dismissal);
            }
            if ($request->has('is_archived') && $request->is_archived !== 'all') {
                $query->where('is_archived', $request->is_archived);
            }

            $data = $query->orderBy('id', 'DESC')->get();

            $data->load(['employee' => function($q) {
                $q->select('employee_code', 'emp_name', 'emp_sal', 'day_price');
            }, 'added', 'updatedby']);

            $employees = Employee::where('com_code', $com_code)->where('functional_status', 1)->select('employee_code', 'emp_name', 'emp_sal', 'day_price')->get();

            return response()->json([
                'status' => true,
                'data' => $data,
                'employees' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $validator = Validator::make($request->all(), [
                'employee_code' => 'required',
                'emp_sal' => 'required',
                'total' => 'required|numeric',
                'months_number' => 'required|integer|min:1',
                'monthly_installment_value' => 'required|numeric',
                'year_and_month_start_date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $employee = Employee::where('com_code', $com_code)->where('employee_code', $request->employee_code)->where('functional_status', 1)->first();
            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'الموظف غير موجود أو موقوف'], 404);
            }

            DB::beginTransaction();

            $year_and_month_start = date("Y-m", strtotime($request->year_and_month_start_date));

            $loan = Main_salary_employee_permanent_loan::create([
                'com_code' => $com_code,
                'employee_code' => $request->employee_code,
                'emp_sal' => $request->emp_sal,
                'total' => $request->total,
                'months_number' => $request->months_number,
                'monthly_installment_value' => $request->monthly_installment_value,
                'year_and_month_start_date' => $request->year_and_month_start_date,
                'year_and_month_start' => $year_and_month_start,
                'total_remain' => $request->total,
                'notes' => $request->notes,
                'added_by' => $user->id,
            ]);

            if ($loan) {
                $i = 1;
                $effectivedate = $year_and_month_start;
                while ($i <= $request->months_number) {
                    Main_salary_p_loans_installment::create([
                        'com_code' => $com_code,
                        'employee_code' => $request->employee_code,
                        'main_salary_p_loans_id' => $loan->id,
                        'monthly_installment_value' => $request->monthly_installment_value,
                        'year_and_month' => $effectivedate,
                        'added_by' => $user->id,
                    ]);
                    $i++;
                    $effectivedate = date('Y-m', strtotime('+1 months', strtotime($effectivedate)));
                }
            }

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم إضافة السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $loan = Main_salary_employee_permanent_loan::where('com_code', $com_code)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            if ($loan->is_archived != 0 || $loan->is_dismissal != 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن تعديل سلفة تم صرفها أو أرشفتها'], 422);
            }

            DB::beginTransaction();

            $old_total = $loan->total;
            $old_months = $loan->months_number;
            $old_installment = $loan->monthly_installment_value;
            $old_start_date = $loan->year_and_month_start_date;

            $year_and_month_start = date("Y-m", strtotime($request->year_and_month_start_date));

            $loan->update([
                'employee_code' => $request->employee_code,
                'emp_sal' => $request->emp_sal,
                'total' => $request->total,
                'months_number' => $request->months_number,
                'monthly_installment_value' => $request->monthly_installment_value,
                'year_and_month_start_date' => $request->year_and_month_start_date,
                'year_and_month_start' => $year_and_month_start,
                'total_remain' => $request->total,
                'notes' => $request->notes,
                'updated_by' => $user->id,
            ]);

            // Re-generate installments if any related field changed
            if ($old_total != $request->total || $old_months != $request->months_number || $old_installment != $request->monthly_installment_value || $old_start_date != $request->year_and_month_start_date) {
                
                Main_salary_p_loans_installment::where('com_code', $com_code)->where('main_salary_p_loans_id', $id)->delete();
                
                $i = 1;
                $effectivedate = $year_and_month_start;
                while ($i <= $request->months_number) {
                    Main_salary_p_loans_installment::create([
                        'com_code' => $com_code,
                        'employee_code' => $request->employee_code,
                        'main_salary_p_loans_id' => $id,
                        'monthly_installment_value' => $request->monthly_installment_value,
                        'year_and_month' => $effectivedate,
                        'added_by' => $user->id,
                    ]);
                    $i++;
                    $effectivedate = date('Y-m', strtotime('+1 months', strtotime($effectivedate)));
                }
            } else {
                Main_salary_p_loans_installment::where('com_code', $com_code)
                    ->where('main_salary_p_loans_id', $id)
                    ->update([
                        'employee_code' => $request->employee_code
                    ]);
            }

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم تعديل السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $loan = Main_salary_employee_permanent_loan::where('com_code', $com_code)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            if ($loan->is_archived != 0) {
                return response()->json(['status' => false, 'message' => 'تم أرشفة هذه السلفة بالفعل'], 422);
            }
            if ($loan->is_dismissal != 0) {
                return response()->json(['status' => false, 'message' => 'تم صرف هذه السلفة بالفعل ولا يمكن حذفها'], 422);
            }

            DB::beginTransaction();

            Main_salary_p_loans_installment::where('com_code', $com_code)->where('main_salary_p_loans_id', $id)->delete();
            $loan->delete();

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم حذف السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function dismiss(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $loan = Main_salary_employee_permanent_loan::where('com_code', $com_code)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            if ($loan->is_archived != 0) {
                return response()->json(['status' => false, 'message' => 'تم أرشفة هذه السلفة بالفعل'], 422);
            }
            if ($loan->is_dismissal != 0) {
                return response()->json(['status' => false, 'message' => 'تم صرف هذه السلفة بالفعل'], 422);
            }

            DB::beginTransaction();

            $loan->update([
                'is_dismissal' => 1,
                'dismissal_by' => $user->id,
                'dismissal_at' => date('Y-m-d H:i:s'),
                'updated_by' => $user->id,
            ]);

            Main_salary_p_loans_installment::where('com_code', $com_code)->where('main_salary_p_loans_id', $id)
                ->update(['is_parent_dismissal' => 1]);

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم صرف السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function installments(Request $request, $id)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;

            $loan = Main_salary_employee_permanent_loan::where('com_code', $com_code)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            $installments = Main_salary_p_loans_installment::where('com_code', $com_code)->where('main_salary_p_loans_id', $id)->orderBy('id', 'ASC')->get();
            $installments->load(['added', 'updatedby']);

            return response()->json([
                'status' => true,
                'data' => $loan,
                'installments' => $installments
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
