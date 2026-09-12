<?php

namespace App\Http\Controllers\Api\Company\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\Finance_calenders;
use App\Models\Finance_months_periods;
use App\Models\Main_salary_employee;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Get employee's attendance status for today, branch coordinates, and shift info.
     */
    public function todayStatus(Request $request)
    {
        try {
            $user = $request->user();
            $employee = $user->employee;
            if (!$employee && $user->employee_id) {
                $employee = Employee::find($user->employee_id);
            }

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.no_employee_profile_linked')
                ], 404);
            }

            $company_id = $user->company_id;
            $today = Carbon::today()->format('Y-m-d');
            $now = Carbon::now();

            // 1. Branch Details & Geofence
            $branch = $employee->branch ? [
                'id' => $employee->branch->id,
                'name' => $employee->branch->name,
                'address' => $employee->branch->address,
                'latitude' => $employee->branch->latitude ? (float)$employee->branch->latitude : null,
                'longitude' => $employee->branch->longitude ? (float)$employee->branch->longitude : null,
                'geofence_radius' => $employee->branch->geofence_radius ? (int)$employee->branch->geofence_radius : 100,
                'is_geofence_configured' => ($employee->branch->latitude !== null && $employee->branch->longitude !== null),
            ] : null;

            // 2. Shift Details
            $shift = $employee->shift ? [
                'id' => $employee->shift->id,
                'type' => $employee->shift->type,
                'from_time' => $employee->shift->from_time,
                'to_time' => $employee->shift->to_time,
                'total_hour' => (float)$employee->shift->total_hour,
            ] : null;

            // 3. Current Open Finance Period
            $currentPeriod = Finance_months_periods::with('month')
                ->where('company_id', $company_id)
                ->where('is_open', 1)
                ->first();

            // 4. Today's Raw Punches
            $todayPunches = AttendanceRawLog::where('company_id', $company_id)
                ->where('employee_code', $employee->employee_code)
                ->whereDate('actionDateTime', $today)
                ->orderBy('actionDateTime', 'ASC')
                ->get(['id', 'actionDateTime', 'actionType', 'actionMethod', 'created_at']);

            $firstCheckIn = null;
            $lastCheckOut = null;
            $hasCheckIn = false;
            $hasCheckOut = false;

            foreach ($todayPunches as $punch) {
                if ($punch->actionType == 1 && !$firstCheckIn) {
                    $firstCheckIn = Carbon::parse($punch->actionDateTime)->format('H:i:s');
                    $hasCheckIn = true;
                }
                if ($punch->actionType == 2) {
                    $lastCheckOut = Carbon::parse($punch->actionDateTime)->format('H:i:s');
                    $hasCheckOut = true;
                }
            }

            // If punches exist without explicit actionType 1, use first punch as check-in
            if (!$hasCheckIn && $todayPunches->isNotEmpty()) {
                $firstCheckIn = Carbon::parse($todayPunches->first()->actionDateTime)->format('H:i:s');
                $hasCheckIn = true;
            }

            // 5. Check if on Approved Vacation Today
            $onVacation = VacationRequest::where('company_id', $company_id)
                ->where('employee_id', $employee->id)
                ->where('status', 1)
                ->whereDate('from_date', '<=', $today)
                ->whereDate('to_date', '>=', $today)
                ->with('vacationType:id,name')
                ->first();

            // 6. Formatted display info
            $locale = app()->getLocale();
            $dayName = $now->locale($locale)->isoFormat('dddd');
            $formattedDate = $now->format('Y-m-d');

            return response()->json([
                'status' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'emp_name' => $employee->emp_name,
                        'employee_code' => $employee->employee_code,
                        'job_name' => $employee->job ? $employee->job->name : null,
                        'department_name' => $employee->department ? $employee->department->name : null,
                    ],
                    'branch' => $branch,
                    'shift' => $shift,
                    'today' => [
                        'date' => $formattedDate,
                        'day_name' => $dayName,
                        'server_time' => $now->format('H:i:s'),
                        'is_checked_in' => $hasCheckIn,
                        'first_check_in' => $firstCheckIn,
                        'is_checked_out' => $hasCheckOut,
                        'last_check_out' => $lastCheckOut,
                        'punches_count' => $todayPunches->count(),
                        'on_vacation' => $onVacation ? [
                            'vacation_type' => $onVacation->vacationType ? $onVacation->vacationType->name : '',
                            'from_date' => $onVacation->from_date,
                            'to_date' => $onVacation->to_date,
                        ] : null,
                        'punches' => $todayPunches->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'time' => Carbon::parse($p->actionDateTime)->format('h:i:s A'),
                                'datetime' => $p->actionDateTime,
                                'type' => $p->actionType == 1 ? 'check_in' : 'check_out',
                                'type_text' => $p->actionType == 1 ? __('messages.checkin') : __('messages.checkout'),
                                'method' => $p->actionMethod,
                            ];
                        }),
                    ],
                    'current_finance_period' => $currentPeriod ? [
                        'id' => $currentPeriod->id,
                        'name' => ($currentPeriod->month ? $currentPeriod->month->name : '') . ' ' . $currentPeriod->finance_yr,
                        'start_date' => $currentPeriod->start_date_m,
                        'end_date' => $currentPeriod->end_date_m,
                    ] : null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit Mobile Biometric Punch (Check-in / Check-out with Geofencing).
     */
    public function punch(Request $request)
    {
        try {
            $user = $request->user();
            $employee = $user->employee;
            if (!$employee && $user->employee_id) {
                $employee = Employee::find($user->employee_id);
            }

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.no_employee_profile_linked')
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'action_type' => 'required|in:1,2', // 1: Check-in (حضور), 2: Check-out (انصراف)
                'device_id' => 'nullable|string|max:255',
                'device_name' => 'nullable|string|max:255',
                'accuracy' => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $branch = $employee->branch;
            if (!$branch) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.employee_branch_not_found')
                ], 422);
            }

            if ($branch->latitude === null || $branch->longitude === null) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.branch_coordinates_not_configured')
                ], 422);
            }

            // 1. Calculate Geofence Distance using Haversine formula
            $distance = $this->calculateHaversineDistance(
                (float)$request->latitude,
                (float)$request->longitude,
                (float)$branch->latitude,
                (float)$branch->longitude
            );

            $allowedRadius = $branch->geofence_radius ? (int)$branch->geofence_radius : 100;

            if ($distance > $allowedRadius) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.outside_geofence_range', [
                        'distance' => round($distance),
                        'allowed' => $allowedRadius,
                        'branch' => $branch->name,
                    ]),
                    'distance_meters' => round($distance, 1),
                    'allowed_radius_meters' => $allowedRadius,
                    'branch_name' => $branch->name,
                ], 422);
            }

            // 2. Anti-spam protection: prevent rapid identical punches within 60 seconds
            $recentPunch = AttendanceRawLog::where('company_id', $user->company_id)
                ->where('employee_code', $employee->employee_code)
                ->where('actionType', $request->action_type)
                ->where('actionDateTime', '>=', Carbon::now()->subSeconds(60)->format('Y-m-d H:i:s'))
                ->first();

            if ($recentPunch) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.punch_duplicate_spammed')
                ], 422);
            }

            // 3. Resolve Current Open Finance Period
            $now = Carbon::now();
            $today = $now->format('Y-m-d');
            $currentPeriod = Finance_months_periods::where('company_id', $user->company_id)
                ->where('is_open', 1)
                ->first();

            // If no open period, fallback to period containing today's date
            if (!$currentPeriod) {
                $currentPeriod = Finance_months_periods::where('company_id', $user->company_id)
                    ->where('start_date_m', '<=', $today)
                    ->where('end_date_m', '>=', $today)
                    ->first();
            }

            // 4. Resolve Active Salary Record if Period exists
            $mainSalaryId = null;
            if ($currentPeriod) {
                $mainSalary = Main_salary_employee::where([
                    'company_id' => $user->company_id,
                    'finance_month_id' => $currentPeriod->id,
                    'employee_code' => $employee->employee_code,
                    'is_archived' => 0
                ])->first();
                if ($mainSalary) {
                    $mainSalaryId = $mainSalary->id;
                }
            }

            // 5. Store Raw Log
            $log = AttendanceRawLog::create([
                'company_id' => $user->company_id,
                'finance_months_periods_id' => $currentPeriod ? $currentPeriod->id : null,
                'employee_code' => $employee->employee_code,
                'actionDateTime' => $now->format('Y-m-d H:i:s'),
                'actionType' => (int)$request->action_type,
                'actionMethod' => 3, // 1=dynamic, 2=manual, 3=mobile_biometric
                'main_salary_employee_id' => $mainSalaryId,
                'added_by' => $user->id,
            ]);

            $successMessage = ((int)$request->action_type === 1)
                ? __('messages.checkin_successful')
                : __('messages.checkout_successful');

            return response()->json([
                'status' => true,
                'message' => $successMessage,
                'data' => [
                    'id' => $log->id,
                    'action_type' => (int)$request->action_type,
                    'action_type_text' => (int)$request->action_type === 1 ? __('messages.checkin') : __('messages.checkout'),
                    'punch_time' => $now->format('h:i:s A'),
                    'punch_date' => $now->format('Y-m-d'),
                    'server_timestamp' => $now->format('Y-m-d H:i:s'),
                    'distance_meters' => round($distance, 1),
                    'branch_name' => $branch->name,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee's monthly attendance logs, absences, and calendar days.
     */
    public function myAttendance(Request $request)
    {
        try {
            $user = $request->user();
            $employee = $user->employee;
            if (!$employee && $user->employee_id) {
                $employee = Employee::find($user->employee_id);
            }

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.no_employee_profile_linked')
                ], 404);
            }

            $company_id = $user->company_id;

            // 1. Get List of Financial Month Periods for company
            $periodsList = Finance_months_periods::with('month')
                ->where('company_id', $company_id)
                ->orderBy('finance_yr', 'DESC')
                ->orderBy('month_id', 'DESC')
                ->get();

            // 2. Determine Selected Month Period
            $selectedPeriod = null;
            if ($request->filled('month_id')) {
                $selectedPeriod = Finance_months_periods::with('month')
                    ->where(['company_id' => $company_id, 'id' => $request->month_id])
                    ->first();
            }

            if (!$selectedPeriod) {
                // Default to open month
                $selectedPeriod = Finance_months_periods::with('month')
                    ->where(['company_id' => $company_id, 'is_open' => 1])
                    ->first();
            }

            if (!$selectedPeriod && $periodsList->isNotEmpty()) {
                $selectedPeriod = $periodsList->first();
            }

            if (!$selectedPeriod) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.no_active_finance_month')
                ], 404);
            }

            $startDate = $selectedPeriod->start_date_m;
            $endDate = $selectedPeriod->end_date_m;

            // 3. Load Processed Attendances from database
            $attendances = Attendance::where([
                'company_id' => $company_id,
                'employee_code' => $employee->employee_code,
                'finance_months_periods_id' => $selectedPeriod->id,
            ])->get()->keyBy('attendance_date');

            // 4. Load Raw Punch Logs for the period
            $rawLogs = AttendanceRawLog::where('company_id', $company_id)
                ->where('employee_code', $employee->employee_code)
                ->whereBetween('actionDateTime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('actionDateTime', 'ASC')
                ->get();

            $punchesByDate = [];
            foreach ($rawLogs as $log) {
                $dateKey = Carbon::parse($log->actionDateTime)->format('Y-m-d');
                if (!isset($punchesByDate[$dateKey])) {
                    $punchesByDate[$dateKey] = [];
                }
                $punchesByDate[$dateKey][] = [
                    'id' => $log->id,
                    'time' => Carbon::parse($log->actionDateTime)->format('h:i:s A'),
                    'time_24' => Carbon::parse($log->actionDateTime)->format('H:i:s'),
                    'type' => $log->actionType == 1 ? 'check_in' : 'check_out',
                    'method' => $log->actionMethod,
                ];
            }

            // 5. Load Approved Vacations in Period
            $vacations = VacationRequest::where('company_id', $company_id)
                ->where('employee_id', $employee->id)
                ->where('status', 1)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('from_date', [$startDate, $endDate])
                      ->orWhereBetween('to_date', [$startDate, $endDate])
                      ->orWhere(function ($sub) use ($startDate, $endDate) {
                          $sub->where('from_date', '<=', $startDate)
                              ->where('to_date', '>=', $endDate);
                      });
                })
                ->with('vacationType:id,name')
                ->get();

            $vacationDates = [];
            foreach ($vacations as $v) {
                $periodDates = CarbonPeriod::create($v->from_date, $v->to_date);
                foreach ($periodDates as $d) {
                    $vacationDates[$d->format('Y-m-d')] = $v->vacationType ? $v->vacationType->name : __('messages.vacation');
                }
            }

            // 6. Build Calendar Days Array
            $calendarDays = [];
            $totalPresent = 0;
            $totalAbsent = 0;
            $totalVacations = 0;
            $totalWorkHours = 0.0;
            $totalLateMinutes = 0;

            $carbonPeriod = CarbonPeriod::create($startDate, $endDate);
            $locale = app()->getLocale();
            $today = Carbon::today()->format('Y-m-d');

            foreach ($carbonPeriod as $dateObj) {
                $dateStr = $dateObj->format('Y-m-d');
                $att = $attendances[$dateStr] ?? null;
                $dayPunches = $punchesByDate[$dateStr] ?? [];

                $dayName = $dateObj->locale($locale)->isoFormat('dddd');
                $status = 0; // 0: Undefined / Weekend, 1: Present, 2: Absent, 5: Vacation
                $statusText = __('messages.unrecorded');
                $checkIn = null;
                $checkOut = null;
                $workHours = 0.0;
                $lateAttendance = 0;
                $earlyLeave = 0;

                if (isset($vacationDates[$dateStr])) {
                    $status = 5;
                    $statusText = $vacationDates[$dateStr];
                    $totalVacations++;
                } elseif ($att) {
                    $status = (int)$att->attendance_status;
                    $workHours = (float)$att->total_hours;
                    $lateAttendance = (int)$att->late_attendance;
                    $earlyLeave = (int)$att->early_leave;
                    $checkIn = $att->check_in;
                    $checkOut = $att->check_out;

                    if ($status === 1) {
                        $statusText = __('messages.present');
                        $totalPresent++;
                        $totalWorkHours += $workHours;
                        $totalLateMinutes += $lateAttendance;
                    } elseif ($status === 2) {
                        $statusText = __('messages.absent');
                        $totalAbsent++;
                    } elseif ($status === 5) {
                        $statusText = __('messages.vacation');
                        $totalVacations++;
                    }
                } elseif (!empty($dayPunches)) {
                    // Fallback to raw punches if attendance extraction hasn't been run yet
                    $status = 1;
                    $statusText = __('messages.present');
                    $first = $dayPunches[0] ?? null;
                    $last = end($dayPunches);
                    $checkIn = $first ? $first['time_24'] : null;
                    $checkOut = ($last && $last !== $first) ? $last['time_24'] : null;
                    $totalPresent++;
                }

                $calendarDays[] = [
                    'date' => $dateStr,
                    'day_number' => (int)$dateObj->format('j'),
                    'day_name' => $dayName,
                    'is_today' => ($dateStr === $today),
                    'is_future' => ($dateStr > $today),
                    'status' => $status,
                    'status_text' => $statusText,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'total_hours' => $workHours,
                    'late_minutes' => $lateAttendance,
                    'early_leave_minutes' => $earlyLeave,
                    'punches' => $dayPunches,
                ];
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'selected_month' => [
                        'id' => $selectedPeriod->id,
                        'name' => ($selectedPeriod->month ? $selectedPeriod->month->name : '') . ' ' . $selectedPeriod->finance_yr,
                        'year_and_month' => $selectedPeriod->year_and_month,
                        'start_date' => $selectedPeriod->start_date_m,
                        'end_date' => $selectedPeriod->end_date_m,
                        'is_open' => (bool)$selectedPeriod->is_open,
                    ],
                    'available_months' => $periodsList->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'name' => ($p->month ? $p->month->name : '') . ' ' . $p->finance_yr,
                            'year_and_month' => $p->year_and_month,
                            'is_open' => (bool)$p->is_open,
                        ];
                    }),
                    'summary' => [
                        'present_days' => $totalPresent,
                        'absent_days' => $totalAbsent,
                        'vacation_days' => $totalVacations,
                        'total_work_hours' => round($totalWorkHours, 2),
                        'total_late_minutes' => $totalLateMinutes,
                    ],
                    'calendar' => $calendarDays,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate Distance between two GPS coordinates using the Haversine formula (in meters).
     */
    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
