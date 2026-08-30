<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Finance_months_periods;
use App\Models\Main_salary_employee;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 400);
        }

        $employee = $user->employee;
        if (!$employee && $user->employee_id) {
            $employee = Employee::find($user->employee_id);
        }

        $comCode = $user->company_id;
        $employeeCode = $employee ? $employee->employee_code : null;

        $presentDays = 0;
        $absentDays = 0;
        $totalBenefits = 0;
        $totalDeductions = 0;
        $netSalary = 0;
        $salaryRecordId = null;

        $currentMonthInfo = Finance_months_periods::where('company_id', $comCode)
            ->where('is_open', 1)
            ->with('month')
            ->first();

        $currentMonthName = $currentMonthInfo ? ($currentMonthInfo->month->name . ' ' . $currentMonthInfo->finance_yr) : date('Y-m');
        $currentYearMonth = $currentMonthInfo ? $currentMonthInfo->year_and_month : date('Y-m');

        if ($employeeCode && $comCode) {
            // 1. Attendance Summary (Current Month)
            $presentDays = Attendance::where('company_id', $comCode)
                ->where('employee_code', $employeeCode)
                ->where('year_and_month', $currentYearMonth)
                ->where('attendance_status', 1)
                ->count();
                
            $absentDays = Attendance::where('company_id', $comCode)
                ->where('employee_code', $employeeCode)
                ->where('year_and_month', $currentYearMonth)
                ->where('attendance_status', 2)
                ->count();
        }

        // 2. Financial & Salary Permissions
        $hasSalaryPermission = $user->role === 'company_admin' || $user->hasPermissionTo('view_salaries') || $user->hasPermissionTo('view_own_salary');

        if ($hasSalaryPermission && $employeeCode && $comCode && $currentMonthInfo) {
            $salaryRecord = Main_salary_employee::where('company_id', $comCode)
                ->where('employee_code', $employeeCode)
                ->where('finance_month_id', $currentMonthInfo->id)
                ->first();
                
            if ($salaryRecord) {
                $salaryRecordId = $salaryRecord->id;
                $totalBenefits = (float)$salaryRecord->total_benefits;
                $totalDeductions = (float)$salaryRecord->total_deduction;
                $netSalary = (float)$salaryRecord->final_the_net;
            } else {
                $totalBenefits = $employee ? (float)$employee->emp_sal : 0;
                $totalDeductions = 0;
                $netSalary = $employee ? (float)$employee->emp_sal : 0;
            }
        } elseif ($hasSalaryPermission && $employee) {
            $totalBenefits = (float)$employee->emp_sal;
            $totalDeductions = 0;
            $netSalary = (float)$employee->emp_sal;
        }

        return response()->json([
            'status' => true,
            'data' => [
                'employee' => [
                    'emp_code' => $employee ? $employee->employee_code : '-',
                    'emp_name' => $employee ? $employee->emp_name : $user->name,
                    'emp_start_date' => $employee ? $employee->date : '-',
                    'emp_salary' => $employee ? (float)$employee->emp_sal : 0,
                    'job_name' => ($employee && $employee->job) ? $employee->job->name : null,
                ],
                'attendance' => [
                    'present' => $presentDays,
                    'absent' => $absentDays,
                    'current_month' => $currentMonthName,
                    'current_year_month' => $currentYearMonth
                ],
                'financials' => [
                    'has_salary_permission' => $hasSalaryPermission,
                    'permission_message' => $hasSalaryPermission ? null : __('messages.no_salary_view_permission'),
                    'salary_record_id' => $salaryRecordId,
                    'total_benefits' => (float)$totalBenefits,
                    'total_deductions' => (float)$totalDeductions,
                    'net_salary' => (float)$netSalary,
                ]
            ]
        ]);
    }
}
