<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Main_salary_employee_permanent_loan;
use App\Models\Main_salary_p_loans_installment;
use App\Models\Finance_calenders;
use App\Models\Finance_months_periods;
use App\Models\Main_salary_employee;
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
            $company_id = $user->company_id;

            $query = Main_salary_employee_permanent_loan::where('company_id', $company_id);

            if ($request->has('employee_code') && $request->employee_code !== 'all') {
                $query->where('employee_code', $request->employee_code);
            }
            if ($request->has('is_disbursed') && $request->is_disbursed !== 'all') {
                $query->where('is_disbursed', $request->is_disbursed);
            }
            if ($request->has('is_archived') && $request->is_archived !== 'all') {
                $query->where('is_archived', $request->is_archived);
            }

            $data = $query->orderBy('id', 'DESC')->get();

            $data->load(['employee' => function ($q) {
                $q->select('employee_code', 'emp_name', 'emp_sal', 'day_price');
            }, 'added', 'updatedby']);

            $employees = Employee::where([
                'company_id' => $company_id,
            ])
                ->select('employee_code', 'emp_name', 'emp_sal', 'day_price')
                ->get();

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
            $company_id = $user->company_id;

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

            $employee = Employee::where('company_id', $company_id)->where('employee_code', $request->employee_code)->where('functional_status', 1)->first();
            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'الموظف غير موجود أو موقوف'], 404);
            }

            DB::beginTransaction();

            $year_and_month_start = date("Y-m", strtotime($request->year_and_month_start_date));

            $loan = Main_salary_employee_permanent_loan::create([
                'company_id' => $company_id,
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
                        'company_id' => $company_id,
                        'employee_code' => $request->employee_code,
                        'main_salary_p_loans_id' => $loan->id,
                        'monthly_installment_value' => $request->monthly_installment_value,
                        'year_and_month' => $effectivedate,
                        'added_by' => $user->id,
                    ]);
                    $i++;
                    $effectivedate = \Carbon\Carbon::createFromFormat('Y-m', $effectivedate)->startOfMonth()->addMonth()->format('Y-m');
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
            $company_id = $user->company_id;

            $loan = Main_salary_employee_permanent_loan::where('company_id', $company_id)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            if ($loan->is_archived != 0 || $loan->is_disbursed != 0) {
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

                Main_salary_p_loans_installment::where('company_id', $company_id)->where('main_salary_p_loans_id', $id)->delete();

                $i = 1;
                $effectivedate = $year_and_month_start;
                while ($i <= $request->months_number) {
                    Main_salary_p_loans_installment::create([
                        'company_id' => $company_id,
                        'employee_code' => $request->employee_code,
                        'main_salary_p_loans_id' => $id,
                        'monthly_installment_value' => $request->monthly_installment_value,
                        'year_and_month' => $effectivedate,
                        'added_by' => $user->id,
                    ]);
                    $i++;
                    $effectivedate = \Carbon\Carbon::createFromFormat('Y-m', $effectivedate)->startOfMonth()->addMonth()->format('Y-m');
                }
            } else {
                Main_salary_p_loans_installment::where('company_id', $company_id)
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
            $company_id = $user->company_id;

            $loan = Main_salary_employee_permanent_loan::where('company_id', $company_id)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            if ($loan->is_archived != 0) {
                return response()->json(['status' => false, 'message' => 'تم أرشفة هذه السلفة بالفعل'], 422);
            }
            if ($loan->is_disbursed != 0) {
                return response()->json(['status' => false, 'message' => 'تم صرف هذه السلفة بالفعل ولا يمكن حذفها'], 422);
            }

            DB::beginTransaction();

            Main_salary_p_loans_installment::where('company_id', $company_id)->where('main_salary_p_loans_id', $id)->delete();
            $loan->delete();

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم حذف السلفة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function disburse(Request $request, $id)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $loan = Main_salary_employee_permanent_loan::where('company_id', $company_id)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            if ($loan->is_archived != 0) {
                return response()->json(['status' => false, 'message' => 'تم أرشفة هذه السلفة بالفعل'], 422);
            }
            if ($loan->is_disbursed != 0) {
                return response()->json(['status' => false, 'message' => 'تم صرف هذه السلفة بالفعل'], 422);
            }

            DB::beginTransaction();

            $loan->update([
                'is_disbursed' => 1,
                'disbursed_by' => $user->id,
                'disbursed_at' => date('Y-m-d H:i:s'),
                'updated_by' => $user->id,
            ]);

            Main_salary_p_loans_installment::where('company_id', $company_id)->where('main_salary_p_loans_id', $id)
                ->update(['is_parent_disbursed' => 1]);

            $mainSalaryOpen = Main_salary_employee::select('id')->where('company_id', $company_id)->where('employee_code', $loan->employee_code)->where('is_archived', 0)->first();
            if ($mainSalaryOpen) {
                $this->recaculate_main_salary_employee($mainSalaryOpen->id);
            }

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
            $company_id = $user->company_id;

            $loan = Main_salary_employee_permanent_loan::where('company_id', $company_id)->where('id', $id)->first();
            if (!$loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            $installments = Main_salary_p_loans_installment::where('company_id', $company_id)->where('main_salary_p_loans_id', $id)->orderBy('id', 'ASC')->get();
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

    public function payCash(Request $request, $installment_id)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $installment = Main_salary_p_loans_installment::where('company_id', $company_id)
                ->where('id', $installment_id)
                ->first();

            if (!$installment) {
                return response()->json(['status' => false, 'message' => 'القسط غير موجود'], 404);
            }

            $p_loan = Main_salary_employee_permanent_loan::where('company_id', $company_id)
                ->where('id', $installment->main_salary_p_loans_id)
                ->where('employee_code', $installment->employee_code)
                ->first();
            if (!$p_loan) {
                return response()->json(['status' => false, 'message' => 'السلفة غير موجودة'], 404);
            }

            if ($installment->status != 0) {
                return response()->json(['status' => false, 'message' => 'هذا القسط مدفوع بالفعل أو مسدد'], 422);
            }

            if ($installment->is_archived != 0) {
                return response()->json(['status' => false, 'message' => 'هذا القسط مؤرشف بالفعل'], 422);
            }

            $counterbefornotpaid = Main_salary_p_loans_installment::where('id', '<', $installment_id)
                ->where('company_id', $company_id)
                ->where('status', 0)
                ->where('is_archived', 0)
                ->where('main_salary_p_loans_id', $installment->main_salary_p_loans_id)
                ->count();

            if ($counterbefornotpaid > 0) {
                return response()->json(['status' => false, 'message' => 'هذا القسط ليس أول قسط'], 422);
            }

            DB::beginTransaction();

            // Update installment to Paid Cash (2) and archive it (1)
            $installment->update([
                'status' => 2,
                'is_archived' => 1,
                'archived_by' => $user->id,
                'archived_at' => date('Y-m-d H:i:s'),
                'updated_by' => $user->id,
            ]);

            // If there's an open salary record for this employee in the installment's month, recalculate it
            $main_salary_employee = Main_salary_employee::where([
                'company_id' => $company_id,
                'employee_code' => $installment->employee_code,
                'year_and_month' => $installment->year_and_month,
                'is_archived' => 0
            ])->first();

            if ($main_salary_employee) {
                $this->recaculate_main_salary_employee($main_salary_employee->id);
            }

            // Update Permanent loan (total paid, total remain) idempotently
            $paidSum = (float) Main_salary_p_loans_installment::where([
                'main_salary_p_loans_id' => $p_loan->id,
                'company_id' => $company_id,
            ])->whereIn('status', [1, 2])->sum('monthly_installment_value');

            $p_loan->update([
                'total_paid' => $paidSum,
                'total_remain' => max(0, (float) $p_loan->total - $paidSum),
            ]);

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم دفع القسط كاش بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
