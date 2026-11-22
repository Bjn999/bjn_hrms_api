<?php

namespace App\Http\Controllers\Api\Company\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use App\Models\Branche;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Finance_months_periods;
use App\Models\Main_salary_employee;
use App\Models\User;
use App\Models\VacationRequest;

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
        $hasSalaryPermission = $user->can('view_salaries') || $user->can('view_own_salary');

        if ($hasSalaryPermission && $employeeCode && $comCode) {
            $salaryRecord = null;
            if ($currentMonthInfo) {
                $salaryRecord = Main_salary_employee::where('company_id', $comCode)
                    ->where('employee_code', $employeeCode)
                    ->where('finance_month_id', $currentMonthInfo->id)
                    ->first();
            }
            
            if (!$salaryRecord) {
                $salaryRecord = Main_salary_employee::where('company_id', $comCode)
                    ->where('employee_code', $employeeCode)
                    ->orderBy('id', 'desc')
                    ->first();
            }
                
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

        // 3. Vacations Summary
        $vacationRemaining = 0;
        $vacationUsed = 0;
        $vacationPending = 0;
        if ($employee) {
            $balanceService = app(\App\Services\VacationBalanceService::class);
            $bal = $balanceService->calculateForEmployee($employee, (int)date('Y'));
            if ($bal) {
                $vacationRemaining = (float)$bal->remaining_balance;
                $vacationUsed = (float)$bal->used_balance;
                $vacationPending = (float)$bal->pending_balance;
            }
        }

        // 4. Company Overview (for company_admin, hr_manager, or users with view_company_dashboard)
        $companyOverview = null;
        $canViewCompanyDashboard = in_array($user->role, ['company_admin', 'hr_manager']) || $user->checkPermissionTo('view_company_dashboard');
        if ($canViewCompanyDashboard && $comCode) {
            $totalEmployees = Employee::where('company_id', $comCode)->count();
            $activeEmployees = Employee::where('company_id', $comCode)->where('Functiona_status', 1)->count();

            $currentMonthSalaries = 0;
            if ($currentMonthInfo) {
                $currentMonthSalaries = (float)Main_salary_employee::where('company_id', $comCode)
                    ->where('finance_month_id', $currentMonthInfo->id)
                    ->sum('final_the_net');
            }
            if ($currentMonthSalaries == 0) {
                $currentMonthSalaries = (float)Employee::where('company_id', $comCode)
                    ->where('Functiona_status', 1)
                    ->sum('emp_sal');
            }

            $pendingLeaveRequests = VacationRequest::where('company_id', $comCode)
                ->where('status', 'pending')
                ->count();

            $today = date('Y-m-d');
            $absencesToday = Attendance::where('company_id', $comCode)
                ->where('attendance_date', $today)
                ->where('attendance_status', 2)
                ->count();
            $presentToday = Attendance::where('company_id', $comCode)
                ->where('attendance_date', $today)
                ->where('attendance_status', 1)
                ->count();

            $departmentsCount = Department::where('company_id', $comCode)->count();
            $branchesCount = Branche::where('company_id', $comCode)->count();

            $recentLeaveRequests = VacationRequest::where('company_id', $comCode)
                ->with(['employee:id,emp_name,employee_code', 'vacationType:id,name'])
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($req) {
                    return [
                        'id' => $req->id,
                        'emp_name' => $req->employee ? $req->employee->emp_name : '-',
                        'emp_code' => $req->employee ? $req->employee->employee_code : '-',
                        'vacation_type' => $req->vacationType ? $req->vacationType->name : '-',
                        'start_date' => $req->start_date,
                        'end_date' => $req->end_date,
                        'days_count' => $req->days_count,
                        'status' => $req->status,
                    ];
                });

            $companyOverview = [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'current_month_salaries' => round($currentMonthSalaries, 2),
                'pending_leave_requests' => $pendingLeaveRequests,
                'absences_today' => $absencesToday,
                'present_today' => $presentToday,
                'departments_count' => $departmentsCount,
                'branches_count' => $branchesCount,
                'recent_leave_requests' => $recentLeaveRequests,
            ];
        }

        return response()->json([
            'status' => true,
            'data' => [
                'company_overview' => $companyOverview,
                'employee' => [
                    'emp_code' => $employee ? $employee->employee_code : '-',
                    'emp_name' => $employee ? $employee->emp_name : $user->name,
                    'emp_start_date' => $employee ? $employee->emp_start_date : '-',
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
                ],
                'vacations' => [
                    'is_active' => $employee ? (bool)$employee->is_active_for_vaccation : false,
                    'remaining_days' => $vacationRemaining,
                    'used_days' => $vacationUsed,
                    'pending_days' => $vacationPending,
                ]
            ]
        ]);
    }
}
