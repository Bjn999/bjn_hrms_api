<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\CompanySetting;
use App\Models\EmployeeVacationBalance;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacationBalanceService
{
    /**
     * Calculate and sync vacation balance for a specific employee and year.
     */
    public function calculateForEmployee(Employee $employee, int $year = null): ?EmployeeVacationBalance
    {
        $year = $year ?? (int)date('Y');
        $companyId = $employee->company_id;

        $settings = CompanySetting::where('company_id', $companyId)->first();
        $monthlyRate = $settings ? (float)($settings->monthly_vacation_balance ?? 0) : 0.0;
        $waitDays = $settings ? (float)($settings->after_days_begin_vacation ?? 0) : 0.0;
        $initialBalance = $settings ? (float)($settings->first_balance_begin_vacation ?? 0) : 0.0;

        $balance = EmployeeVacationBalance::firstOrNew([
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'year' => $year,
        ]);

        $carried = (float)($balance->carried_over_balance ?? 0.0);

        // Calculate Used Days (Approved requests with deduct_from_balance = 1 in this year)
        $usedDays = (float)VacationRequest::where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->where('status', 1) // Approved
            ->whereYear('from_date', $year)
            ->whereHas('vacationType', function ($q) {
                $q->where('deduct_from_balance', 1);
            })
            ->sum('days_count');

        // Calculate Pending Days
        $pendingDays = (float)VacationRequest::where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->where('status', 0) // Pending
            ->whereYear('from_date', $year)
            ->whereHas('vacationType', function ($q) {
                $q->where('deduct_from_balance', 1);
            })
            ->sum('days_count');

        if (!$employee->is_active_for_vaccation || !$employee->emp_start_date) {
            $balance->carried_over_balance = $carried;
            $balance->initial_assigned_balance = 0.0;
            $balance->monthly_accrued_balance = 0.0;
            $balance->total_entitled_balance = $carried;
            $balance->used_balance = $usedDays;
            $balance->pending_balance = $pendingDays;
            $balance->remaining_balance = round($carried - $usedDays, 2);
            $balance->last_calculated_at = now();
            $balance->save();
            return $balance;
        }

        $startDate = Carbon::parse($employee->emp_start_date);
        $startYear = $startDate->year;
        $currentDate = Carbon::now();

        $assignedInitial = 0.0;
        $accrued = 0.0;

        if ($startYear < $year) {
            // Employee joined before this year
            $monthsElapsed = ($year === (int)date('Y')) ? (int)date('n') : 12;
            $accrued = round($monthsElapsed * $monthlyRate, 2);
        } elseif ($startYear === $year) {
            // Employee joined during this year
            $daysEmployed = $startDate->diffInDays($currentDate);
            if ($daysEmployed >= $waitDays) {
                $assignedInitial = $initialBalance;
                // Calculate accrued months from the completion of wait period to now
                $eligibleDate = $startDate->copy()->addDays($waitDays);
                $eligibleMonth = $eligibleDate->month;
                $currentMonth = (int)date('n');
                $monthsActive = max(0, $currentMonth - $eligibleMonth);
                $accrued = round($monthsActive * $monthlyRate, 2);
            }
        }

        $totalEntitled = round($carried + $assignedInitial + $accrued, 2);
        $remaining = round($totalEntitled - $usedDays, 2);

        $balance->carried_over_balance = $carried;
        $balance->initial_assigned_balance = $assignedInitial;
        $balance->monthly_accrued_balance = $accrued;
        $balance->total_entitled_balance = $totalEntitled;
        $balance->used_balance = $usedDays;
        $balance->pending_balance = $pendingDays;
        $balance->remaining_balance = $remaining;
        $balance->last_calculated_at = now();
        $balance->save();

        return $balance;
    }

    /**
     * Recalculate balances for all active employees of a company.
     */
    public function recalculateCompany(string $companyId, int $year = null): int
    {
        $year = $year ?? (int)date('Y');
        $employees = Employee::where('company_id', $companyId)
            ->where('functional_status', 1)
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            $this->calculateForEmployee($employee, $year);
            $count++;
        }

        return $count;
    }
}
