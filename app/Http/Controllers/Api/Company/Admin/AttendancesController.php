<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Models\Finance_calenders;
use App\Models\Finance_months_periods;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\FingerprintImport;
use DateTimeInterface;
use App\Models\Main_salary_employee;
use App\Models\AttendanceRawLog;
use App\Models\AttendanceAction;
use Exception;
use App\Services\BioTimeService;
use Illuminate\Support\Facades\Log;

class AttendancesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $finance_years = Finance_calenders::where('company_id', $company_id)
                ->whereIn('is_open', [1, 2])
                ->orderBy('finance_yr', 'DESC')
                ->get(['finance_yr']);

            $default_year = $finance_years->first() ? $finance_years->first()->finance_yr : null;
            $filter_year = $request->finance_yr && $request->finance_yr !== 'all' ? $request->finance_yr : $default_year;

            $query = Finance_months_periods::where('company_id', $company_id);

            if ($filter_year) {
                $query->where('finance_yr', $filter_year);
            }

            $data = $query->orderBy('finance_yr', 'DESC')
                ->orderBy('month_id', 'ASC')
                ->with(['month:id,name,name_en'])
                ->get();

            foreach ($data as $info) {
                $info->currentYear = Finance_calenders::where(['company_id' => $company_id, 'finance_yr' => $info->finance_yr])->first(['is_open']);
                $info->counterOpenMonth = Finance_months_periods::with('month')->where(['company_id' => $company_id, 'is_open' => 1])->count();
                $info->counterPreviousMonthWaitingOpen = Finance_months_periods::with('month')->where(['company_id' => $company_id, 'is_open' => 0, 'finance_yr' => $info->finance_yr])
                    ->where('month_id', '<', $info->month_id)->count();
            }

            return response()->json([
                'status' => true,
                'data' => $data,
                'finance_years' => $finance_years,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $finance_month_data = Finance_months_periods::with('month')->where(['company_id' => $company_id, 'id' => $id])->first();
            if (!$finance_month_data) {
                return response()->json(['status' => false, 'message' => 'عفواً غير قادر على الوصول إلى البيانات المطلوبة'], 404);
            }

            // جلب الموظفين بالبيانات الأساسية فقط لتوفير استهلاك الذاكرة
            $employees = Employee::with(['branch:id,name', 'department:id,name', 'job:id,name'])
                ->where('company_id', $company_id)
                ->orderBy('id', 'DESC')
                ->get(['id', 'employee_code', 'zketo_code', 'emp_name', 'functional_status', 'branch_id', 'emp_departments_id', 'emp_job_id']);

            // جلب تاريخ آخر استيراد لملف البصمة لفرز السجلات الخام
            $last_import = AttendanceRawLog::where([
                'company_id' => $company_id,
                'finance_months_periods_id' => $id
            ])->latest('id')->first(['created_at']);

            // جلب تاريخ آخر سجل بصمة تم ارفاقه (أكبر تاريخ حركة)
            $last_fingerprint = AttendanceRawLog::where([
                'company_id' => $company_id,
                'finance_months_periods_id' => $id
            ])->latest('actionDateTime')->first(['actionDateTime']);

            return response()->json([
                'status' => true,
                'finance_month' => $finance_month_data,
                'employees' => $employees,
                'last_import' => $last_import ? $last_import->created_at->format('Y-m-d H:i:s') : null,
                'last_fingerprint_date' => $last_fingerprint ? date('Y-m-d H:i:s', strtotime($last_fingerprint->actionDateTime)) : null
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }




    public function employeeAttendance(Request $request, $monthId, $employeeCode)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $finance_month_data = Finance_months_periods::with('month')->where(['company_id' => $company_id, 'id' => $monthId])->first();
            if (!$finance_month_data) {
                return response()->json(['status' => false, 'message' => 'عفواً غير قادر على الوصول إلى البيانات المطلوبة'], 404);
            }

            $employee = Employee::with(['branch:id,name', 'department:id,name', 'job:id,name'])
                ->where(['company_id' => $company_id, 'employee_code' => $employeeCode])
                ->first();
                
            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], 404);
            }

            $data = Attendance::with(['addedByAdmin:id,name', 'updatedByAdmin:id,name'])
                ->where([
                    'company_id' => $company_id,
                    'finance_months_periods_id' => $monthId,
                    'employee_code' => $employeeCode
                ])
                ->orderBy('attendance_date', 'ASC')
                ->get();

            return response()->json([
                'status' => true,
                'finance_month' => $finance_month_data,
                'employee' => $employee,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function syncBioTime(Request $request, $monthId)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $month = Finance_months_periods::where(['company_id' => $company_id, 'id' => $monthId])->first();
            if (!$month) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي غير موجود'], 404);
            }
            if ($month->is_open != 1) {
                return response()->json(['status' => false, 'message' => 'عفواً، هذا الشهر المالي مغلق ولا يمكن التعديل عليه'], 422);
            }

            // Get all active employees who have attendance enabled and map to zketo_code
            $employees = Employee::where('company_id', $company_id)
                ->where('functional_status', 1)
                ->where('does_has_attendance', 1)
                ->whereNotNull('zketo_code')
                ->get(['employee_code', 'zketo_code', 'emp_name']);

            if ($employees->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'لا يوجد موظفون نشطون لديهم بصمة مفعلة في النظام حالياً'], 422);
            }

            $empMap = [];
            foreach ($employees as $emp) {
                $empMap[(string)$emp->zketo_code] = [
                    'employee_code' => $emp->employee_code,
                    'emp_name' => $emp->emp_name
                ];
            }

            $startDate = $month->start_date_for_pasma;
            $endDate = $month->end_date_for_pasma;

            // Call BioTime Service
            $bioTimeService = new BioTimeService();
            // Use company_id cast to int as the area code in BioTime, matching createEmployee behavior
            $transactions = $bioTimeService->fetchTransactions($startDate, $endDate, (int)$company_id);

            $successCount = 0;
            $ignoredDuplicate = 0;
            $ignoredOutsidePeriod = 0;
            $ignoredEmployeeNotFound = 0;

            DB::beginTransaction();

            foreach ($transactions as $tx) {
                $emp_code = trim((string)($tx['emp_code'] ?? ''));
                $punch_time = $tx['punch_time'] ?? '';
                $punch_state = strtolower(trim((string)($tx['punch_state'] ?? ''))); 

                if (empty($emp_code) || empty($punch_time)) {
                    continue;
                }

                // Check if employee belongs to this company
                $employee_info = $empMap[$emp_code] ?? null;
                if (!$employee_info) {
                    $ignoredEmployeeNotFound++;
                    continue;
                }

                $employee_code = $employee_info['employee_code'];

                // Parse actionDateTime
                $time = strtotime($punch_time);
                if ($time === false) {
                    continue;
                }
                $dateStr = date('Y-m-d', $time);
                $fullDateTimeStr = date('Y-m-d H:i:s', $time);

                // Check date period
                if ($dateStr < $startDate || $dateStr > $endDate) {
                    $ignoredOutsidePeriod++;
                    continue;
                }

                // Map punch_state: 0/in/c/in/dokhool = 1 (حضور), 1/out/c/out/khorooj = 2 (انصراف)
                $actionType = 1; // Default Check-in
                if ($punch_state === '1' || $punch_state === 'out' || str_contains($punch_state, 'exit') || str_contains($punch_state, 'خروج') || str_contains($punch_state, 'انصراف')) {
                    $actionType = 2; // Check-out
                }

                // Check if raw log already exists to avoid duplicates
                $exists = AttendanceRawLog::where([
                    'company_id' => $company_id,
                    'employee_code' => $employee_code,
                    'actionDateTime' => $fullDateTimeStr,
                    'actionType' => $actionType
                ])->exists();

                if ($exists) {
                    $ignoredDuplicate++;
                    continue;
                }

                // Get open salary record
                $mainSalary = Main_salary_employee::where([
                    'company_id' => $company_id,
                    'finance_month_id' => $monthId,
                    'employee_code' => $employee_code,
                    'is_archived' => 0
                ])->first();

                // Insert raw log
                AttendanceRawLog::create([
                    'company_id' => $company_id,
                    'finance_months_periods_id' => $monthId,
                    'employee_code' => $employee_code,
                    'actionDateTime' => $fullDateTimeStr,
                    'actionType' => $actionType,
                    'actionMethod' => 1, // 1 = dynamic/automatic from BioTime API
                    'main_salary_employee_id' => $mainSalary ? $mainSalary->id : null,
                    'added_by' => $user->id
                ]);

                $successCount++;
            }

            // Execute extraction to recalculate attendance for all active employees of this company
            $activeEmployees = Employee::where('company_id', $company_id)
                ->where('functional_status', 1)
                ->where('does_has_attendance', 1)
                ->get();

            foreach ($activeEmployees as $emp) {
                $this->extractSingleEmployee($company_id, $monthId, $emp->employee_code, $user->id);
            }

            DB::commit();

            $message = "تمت عملية مزامنة الحركات من BioTime بنجاح. ";
            $message .= "تم استيراد $successCount حركة بصمة جديدة.";
            if ($ignoredDuplicate > 0) {
                $message .= " (تم تخطي $ignoredDuplicate حركة مكررة موجودة بالفعل)";
            }
            if ($ignoredOutsidePeriod > 0) {
                $message .= " (تم تجاهل $ignoredOutsidePeriod حركة لوقوعها خارج فترة الشهر المالي)";
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'added' => $successCount,
                    'ignored_duplicate' => $ignoredDuplicate,
                    'ignored_outside' => $ignoredOutsidePeriod,
                    'ignored_emp' => $ignoredEmployeeNotFound
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("خطأ أثناء مزامنة البصمات من BioTime: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function extractMonthActions(Request $request, $monthId)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $month = Finance_months_periods::where(['company_id' => $company_id, 'id' => $monthId])->first();
            if (!$month) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي غير موجود'], 404);
            }
            if ($month->is_open != 1) {
                return response()->json(['status' => false, 'message' => 'عفواً، هذا الشهر المالي مغلق ولا يمكن التعديل عليه'], 422);
            }

            // Get all active employees who have attendance enabled
            $employees = Employee::where('company_id', $company_id)
                ->where('functional_status', 1)
                ->where('does_has_attendance', 1)
                ->get();

            DB::beginTransaction();

            foreach ($employees as $emp) {
                $this->extractSingleEmployee($company_id, $monthId, $emp->employee_code, $user->id);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم استخراج وحساب بصمات الموظفين بنجاح وتحديث الحركات والسجلات الحالية'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function extractEmployeeActions(Request $request, $monthId, $employeeCode)
    {
        try {
            $user = $request->user();
            $company_id = $user->company_id;

            $month = Finance_months_periods::where(['company_id' => $company_id, 'id' => $monthId])->first();
            if (!$month) {
                return response()->json(['status' => false, 'message' => 'الشهر المالي غير موجود'], 404);
            }
            if ($month->is_open != 1) {
                return response()->json(['status' => false, 'message' => 'عفواً، هذا الشهر المالي مغلق ولا يمكن التعديل عليه'], 422);
            }

            DB::beginTransaction();

            $this->extractSingleEmployee($company_id, $monthId, $employeeCode, $user->id);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم استخراج وحساب بصمات الموظف بنجاح وتحديث حركات البصمة وسجل الحضور'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    private function extractSingleEmployee($company_id, $monthId, $employeeCode, $adminId)
    {
        $month = Finance_months_periods::where(['company_id' => $company_id, 'id' => $monthId])->first();
        if (!$month) {
            throw new Exception("الشهر المالي غير موجود");
        }

        $employee = Employee::where(['company_id' => $company_id, 'employee_code' => $employeeCode])->first();
        if (!$employee) {
            throw new Exception("الموظف غير موجود");
        }

        // Get settings to check delay & early departure configuration
        $settings = DB::table('admin_panel_settings')->where('company_id', $company_id)->first();
        $delayLimit = $settings ? (float)$settings->after_miniute_calculate_delay : 0.0;
        $earlyLeaveLimit = $settings ? (float)$settings->after_miniute_calculate_early_departure : 0.0;

        // Get main salary record if any
        $mainSalary = Main_salary_employee::where([
            'company_id' => $company_id,
            'finance_month_id' => $monthId,
            'employee_code' => $employeeCode,
        ])->first();

        $mainSalaryId = $mainSalary ? $mainSalary->id : null;
        $startDate = $month->start_date_for_pasma;
        $endDate = $month->end_date_for_pasma;

        // Fetch active holidays/occasions in this period
        $occasions = DB::table('occasions')
            ->where('company_id', $company_id)
            ->where('active', 1)
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('from_date', [$startDate, $endDate])
                  ->orWhereBetween('to_date', [$startDate, $endDate]);
            })
            ->get();

        // Generate all dates in period and ensure Attendance record exists
        $dates = [];
        $currentDate = $startDate;
        while (strtotime($currentDate) <= strtotime($endDate)) {
            $dates[] = $currentDate;
            
            // Get employee shift hours for this day
            $isFixed = $employee->is_has_fixed_shift == 1;
            $shiftHours = 0.0;
            if ($isFixed && $employee->shift_type_id) {
                $shift = DB::table('shifts_types')->where('id', $employee->shift_type_id)->first();
                $shiftHours = $shift ? (float)$shift->total_hour : 0.0;
            } else {
                $shiftHours = (float)($employee->daily_work_hour ?? 8.0);
            }

            // Find or create attendance
            Attendance::updateOrCreate([
                'company_id' => $company_id,
                'employee_code' => $employeeCode,
                'finance_months_periods_id' => $monthId,
                'attendance_date' => $currentDate,
            ], [
                'main_salary_employee_id' => $mainSalaryId,
                'shift_hours' => $shiftHours,
                'added_by' => $adminId,
                'branch_id' => $employee->branch_id,
                'functional_status' => $employee->functional_status,
                'year_and_month' => date('Y-m', strtotime($currentDate))
            ]);

            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }

        // Get all attendances to map to IDs
        $attendances = Attendance::where([
            'company_id' => $company_id,
            'employee_code' => $employeeCode,
            'finance_months_periods_id' => $monthId,
        ])->get()->keyBy('attendance_date');

        // Delete previous dynamic actions for this employee for this month
        $attendanceIds = $attendances->pluck('id')->toArray();
        AttendanceAction::whereIn('attendance_id', $attendanceIds)
            ->where('actionMethod', 1) // dynamic
            ->delete();

        // Process shifts matching raw logs
        $isFixed = $employee->is_has_fixed_shift == 1;
        $shift = null;
        if ($isFixed && $employee->shift_type_id) {
            $shift = DB::table('shifts_types')->where('id', $employee->shift_type_id)->first();
        }
        $dailyWorkHour = (float)($employee->daily_work_hour ?? 8.0);

        $extractedShifts = [];

        if ($isFixed && $shift) {
            $fromTime = $shift->from_time;
            $toTime = $shift->to_time;
            $isOvernight = strtotime($toTime) < strtotime($fromTime);

            foreach ($dates as $date) {
                if ($isOvernight) {
                    $shiftStartStr = $date . ' ' . $fromTime;
                    $shiftEndStr = date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $toTime;
                    
                    $shiftStartTimestamp = strtotime($shiftStartStr);
                    $shiftEndTimestamp = strtotime($shiftEndStr);
                    
                    $checkInStart = date('Y-m-d H:i:s', $shiftStartTimestamp - 4 * 3600);
                    $checkInEnd = date('Y-m-d H:i:s', $shiftStartTimestamp + 4 * 3600);
                    
                    $checkInLog = AttendanceRawLog::where('employee_code', $employeeCode)
                        ->where('company_id' , $company_id)
                        ->whereBetween('actionDateTime', [$checkInStart, $checkInEnd])
                        ->orderBy('actionDateTime', 'ASC')
                        ->first();
                        
                    if ($checkInLog) {
                        $checkInTime = $checkInLog->actionDateTime;
                        
                        $checkOutEnd = date('Y-m-d H:i:s', $shiftEndTimestamp + 3 * 3600);
                        
                        $checkOutLog = AttendanceRawLog::where('employee_code', $employeeCode)
                            ->where('company_id', $company_id)
                            ->where('actionDateTime', '>', $checkInTime)
                            ->where('actionDateTime', '<=', $checkOutEnd)
                            ->orderBy('actionDateTime', 'DESC')
                            ->first();
                            
                        $checkOutTime = $checkOutLog ? $checkOutLog->actionDateTime : null;
                        
                        $extractedShifts[] = [
                            'check_in' => $checkInTime,
                            'check_out' => $checkOutTime,
                            'workday_date' => $date
                        ];
                    }
                } else {
                    $dayStart = $date . ' 00:00:00';
                    $dayEnd = $date . ' 23:59:59';
                    
                    $logs = AttendanceRawLog::where('employee_code', $employeeCode)
                        ->where('company_id', $company_id)
                        ->whereBetween('actionDateTime', [$dayStart, $dayEnd])
                        ->orderBy('actionDateTime', 'ASC')
                        ->get();
                        
                    if ($logs->count() > 0) {
                        $checkInTime = $logs->first()->actionDateTime;
                        $checkOutTime = $logs->count() > 1 ? $logs->last()->actionDateTime : null;
                        
                        $extractedShifts[] = [
                            'check_in' => $checkInTime,
                            'check_out' => $checkOutTime,
                            'workday_date' => $date
                        ];
                    }
                }
            }
        } else {
            // Flexible/Variable Shift
            $searchEnd = date('Y-m-d H:i:s', strtotime($endDate . ' +1 day 23:59:59'));
            $logs = AttendanceRawLog::where('employee_code', $employeeCode)
                ->where('company_id', $company_id)
                ->whereBetween('actionDateTime', [$startDate . ' 00:00:00', $searchEnd])
                ->orderBy('actionDateTime', 'ASC')
                ->get();
                
            $activeCheckIn = null;
            $activeCheckOut = null;
            $thresholdHours = max(12.0, $dailyWorkHour + 4.0);
            
            foreach ($logs as $log) {
                $logTime = strtotime($log->actionDateTime);
                
                if ($activeCheckIn === null) {
                    $activeCheckIn = $log;
                } else {
                    $checkInTime = strtotime($activeCheckIn->actionDateTime);
                    $diffHours = ($logTime - $checkInTime) / 3600.0;
                    
                    if ($diffHours <= $thresholdHours) {
                        $activeCheckOut = $log;
                    } else {
                        // Exceeded threshold, finalize current shift with its check-out
                        $extractedShifts[] = [
                            'check_in' => $activeCheckIn->actionDateTime,
                            'check_out' => $activeCheckOut ? $activeCheckOut->actionDateTime : null,
                            'workday_date' => date('Y-m-d', strtotime($activeCheckIn->actionDateTime))
                        ];
                        // start new shift
                        $activeCheckIn = $log;
                        $activeCheckOut = null;
                    }
                }
            }
            if ($activeCheckIn !== null) {
                $extractedShifts[] = [
                    'check_in' => $activeCheckIn->actionDateTime,
                    'check_out' => $activeCheckOut ? $activeCheckOut->actionDateTime : null,
                    'workday_date' => date('Y-m-d', strtotime($activeCheckIn->actionDateTime))
                ];
            }
        }

        // Group shifts by workday_date
        $groupedShifts = [];
        foreach ($extractedShifts as $s) {
            $date = $s['workday_date'];
            if ($date < $startDate || $date > $endDate) {
                continue;
            }
            $groupedShifts[$date][] = $s;
        }

        // Save to attendance_actions and update attendances
        foreach ($dates as $date) {
            $attendance = $attendances->get($date);
            if (!$attendance) continue;

            $shiftsList = $groupedShifts[$date] ?? [];

            // Create dynamic actions for this date
            foreach ($shiftsList as $s) {
                $checkIn = $s['check_in'];
                $checkOut = $s['check_out'];

                // Check-in action
                AttendanceAction::create([
                    'company_id' => $company_id,
                    'finance_months_periods_id' => $monthId,
                    'attendance_id' => $attendance->id,
                    'employee_code' => $employeeCode,
                    'actionDateTime' => $checkIn,
                    'actionType' => 1, // حضور
                    'actionMethod' => 1, // dynamic
                    'it_is_active_with_parent' => 0, 
                    'is_processed' => 0,
                    'added_by' => $adminId
                ]);

                // Check-out action (if any)
                if ($checkOut !== null) {
                    AttendanceAction::create([
                        'company_id' => $company_id,
                        'finance_months_periods_id' => $monthId,
                        'attendance_id' => $attendance->id,
                        'employee_code' => $employeeCode,
                        'actionDateTime' => $checkOut,
                        'actionType' => 2, // انصراف
                        'actionMethod' => 1, // dynamic
                        'it_is_active_with_parent' => 0, 
                        'is_processed' => 0,
                        'added_by' => $adminId
                    ]);
                }
            }

            // Retrieve all actions (both dynamic and manual) for this attendance day
            $allActions = AttendanceAction::where('attendance_id', $attendance->id)
                ->orderBy('actionDateTime', 'ASC')
                ->get();

            $earliestCheckIn = null;
            $latestCheckOut = null;
            $totalHours = 0.0;
            $currentCheckIn = null;

            foreach ($allActions as $act) {
                if ($act->actionType == 1) {
                    if ($earliestCheckIn === null || strtotime($act->actionDateTime) < strtotime($earliestCheckIn)) {
                        $earliestCheckIn = $act->actionDateTime;
                    }
                    $currentCheckIn = $act->actionDateTime;
                } else if ($act->actionType == 2) {
                    if ($latestCheckOut === null || strtotime($act->actionDateTime) > strtotime($latestCheckOut)) {
                        $latestCheckOut = $act->actionDateTime;
                    }
                    if ($currentCheckIn !== null) {
                        $totalHours += (strtotime($act->actionDateTime) - strtotime($currentCheckIn)) / 3600.0;
                        $currentCheckIn = null;
                    }
                }
            }

            // Mark active with parent
            if ($earliestCheckIn !== null) {
                AttendanceAction::where('attendance_id', $attendance->id)
                    ->where('actionType', 1)
                    ->update(['it_is_active_with_parent' => 0]);
                    
                AttendanceAction::where('attendance_id', $attendance->id)
                    ->where('actionType', 1)
                    ->where('actionDateTime', $earliestCheckIn)
                    ->limit(1)
                    ->update(['it_is_active_with_parent' => 1]);
            }

            if ($latestCheckOut !== null) {
                AttendanceAction::where('attendance_id', $attendance->id)
                    ->where('actionType', 2)
                    ->update(['it_is_active_with_parent' => 0]);

                AttendanceAction::where('attendance_id', $attendance->id)
                    ->where('actionType', 2)
                    ->where('actionDateTime', $latestCheckOut)
                    ->limit(1)
                    ->update(['it_is_active_with_parent' => 1]);
            }

            // --- Calculation of attendance metrics, delays, early leaves, and absences ---
            $late_attendance = 0;
            $early_leave = 0;
            $absence_hours = 0.0;
            $attendance_status = 1; // 1 = حضور كامل
            $occ_id = null;

            // Get standard shift hours
            $isFixed = $employee->is_has_fixed_shift == 1;
            $shiftHours = 0.0;
            if ($isFixed && $employee->shift_type_id) {
                $shiftHours = $shift ? (float)$shift->total_hour : 0.0;
            } else {
                $shiftHours = (float)($employee->daily_work_hour ?? 8.0);
            }

            // Check if there are any logs for this date
            if ($earliestCheckIn === null) {
                // No logs! Check if holiday or weekend
                $dayOfWeek = date('N', strtotime($date)); // 5 is Friday
                
                // Check if this date falls within any active holiday/occasion
                $matchingOccasion = $occasions->first(function($occ) use ($date) {
                    return $date >= $occ->from_date && $date <= $occ->to_date;
                });

                if ($matchingOccasion) {
                    $attendance_status = 5; // إجازة رسمية
                    $occ_id = $matchingOccasion->id;
                    $shiftHours = 0.0;
                } elseif ($dayOfWeek == 5) {
                    $attendance_status = 5; // إجازة أسبوعية (Friday)
                    $shiftHours = 0.0;
                } else {
                    $attendance_status = 2; // غياب
                    $absence_hours = $shiftHours;
                }
            } else {
                // We have check-in! Let's check check-out status
                if ($latestCheckOut === null) {
                    // Clocked in but didn't clock out (Missing punch)
                    $early_leave = 1; // بصم حضور ولم يبصم انصراف
                    $absence_hours = $shiftHours; // يعتبر غياب الساعات لعدم إتمام بصمة الانصراف
                    $attendance_status = 4; // انصراف مبكر / نقص بصمة
                    $totalHours = 0.0;
                } else {
                    // Both check-in and check-out exist
                    $totalHours = round($totalHours, 2);

                    if ($isFixed && $shift) {
                        // Check delay (تأخير)
                        $shiftStartStr = $date . ' ' . $shift->from_time;
                        $shiftStartTimestamp = strtotime($shiftStartStr);
                        $checkInTimestamp = strtotime($earliestCheckIn);

                        $delaySeconds = $checkInTimestamp - $shiftStartTimestamp;
                        if ($delaySeconds > ($delayLimit * 60)) {
                            $late_attendance = 1;
                            $attendance_status = 3; // تأخير
                        }

                        // Check early leave (انصراف مبكر)
                        $shiftEndStr = $date . ' ' . $shift->to_time;
                        if (strtotime($shift->to_time) < strtotime($shift->from_time)) {
                            // overnight shift
                            $shiftEndStr = date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $shift->to_time;
                        }
                        $shiftEndTimestamp = strtotime($shiftEndStr);
                        $checkOutTimestamp = strtotime($latestCheckOut);

                        $earlyLeaveSeconds = $shiftEndTimestamp - $checkOutTimestamp;
                        if ($earlyLeaveSeconds > ($earlyLeaveLimit * 60)) {
                            $early_leave = 1;
                            // If status is not already late (3), set it to early leave (4)
                            if ($attendance_status == 1) {
                                $attendance_status = 4;
                            }
                        }
                    } else {
                        // Flexible shift: compare total_hours with required daily hours
                        if ($totalHours < $shiftHours) {
                            $early_leave = 1;
                            $absence_hours = round($shiftHours - $totalHours, 2);
                            $attendance_status = 4; // انصراف مبكر / نقص ساعات
                        }
                    }
                }
            }

            // Update parent Attendance record with calculated statistics
            $attendance->update([
                'check_in' => $earliestCheckIn,
                'check_out' => $latestCheckOut,
                'total_hours' => $totalHours,
                'late_attendance' => $late_attendance,
                'early_leave' => $early_leave,
                'absence_hours' => $absence_hours,
                'attendance_status' => $attendance_status,
                'occasions_id' => $occ_id,
                'actionDateTime' => $earliestCheckIn,
                'updated_by' => $adminId
            ]);
        }
    }
}