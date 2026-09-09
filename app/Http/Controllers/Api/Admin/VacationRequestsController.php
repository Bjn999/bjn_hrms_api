<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\VacationRequest;
use App\Models\VacationType;
use App\Models\Attendance;
use App\Models\Finance_months_periods;
use App\Services\VacationBalanceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VacationRequestsController extends Controller
{
    protected $balanceService;

    public function __construct(VacationBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    /**
     * List all vacation requests for HR / Company Admin
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $company_id = $user->company_id;
        $isManager = ($user->role === 'company_admin' || $user->role === 'hr_manager' || $user->hasPermissionTo('view_employees') || $user->hasPermissionTo('view_occasions'));

        $query = VacationRequest::where('company_id', $company_id)
            ->with([
                'employee:id,emp_name,employee_code,emp_departments_id,emp_job_id,branch_id',
                'employee.department:id,name',
                'employee.job:id,name',
                'vacationType:id,name,type_slug,is_paid,deduct_from_balance',
                'actionByUser:id,name'
            ]);

        // If regular employee without management permissions, restrict strictly to their own requests
        if (!$isManager) {
            $query->where('employee_id', $user->employee_id ?? 0);
        } else {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('vacation_type_id')) {
            $query->where('vacation_type_id', $request->vacation_type_id);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('from_date', [$request->from_date, $request->to_date])
                  ->orWhereBetween('to_date', [$request->from_date, $request->to_date]);
            });
        }

        $data = $query->orderBy('id', 'DESC')->get();

        // Calculate personal balance for current user if employee
        $myBalance = null;
        if ($user->employee_id) {
            $emp = Employee::find($user->employee_id);
            if ($emp) {
                $myBalance = $this->balanceService->calculateForEmployee($emp, (int)date('Y'));
            }
        }

        return response()->json([
            'status' => true,
            'is_manager' => $isManager,
            'my_balance' => $myBalance,
            'data' => $data
        ]);
    }

    /**
     * List requests & balance for the authenticated employee
     */
    public function myRequests(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee_id ? Employee::find($user->employee_id) : null;

        if (!$employee) {
            return response()->json([
                'status' => true,
                'balance' => null,
                'data' => [],
                'message' => __('messages.no_employee_profile_linked')
            ]);
        }

        $year = (int)($request->year ?? date('Y'));
        $balance = $this->balanceService->calculateForEmployee($employee, $year);

        $requests = VacationRequest::where('company_id', $user->company_id)
            ->where('employee_id', $employee->id)
            ->with(['vacationType:id,name,type_slug,is_paid,deduct_from_balance', 'actionByUser:id,name'])
            ->orderBy('id', 'DESC')
            ->get();

        return response()->json([
            'status' => true,
            'balance' => $balance,
            'data' => $requests
        ]);
    }

    /**
     * Store new vacation request (Submitted by employee or HR on behalf of employee)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $company_id = $user->company_id;

        $validator = Validator::make($request->all(), [
            'employee_id' => 'nullable|exists:employees,id',
            'vacation_type_id' => 'required|exists:vacation_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Determine target employee
        $employeeId = $request->employee_id;
        if (!$employeeId) {
            $employeeId = $user->employee_id;
        }

        if (!$employeeId) {
            return response()->json([
                'status' => false,
                'message' => __('messages.employee_not_found')
            ], 404);
        }

        $employee = Employee::where('company_id', $company_id)->where('id', $employeeId)->first();
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => __('messages.employee_not_found')
            ], 404);
        }

        $vacationType = VacationType::where('company_id', $company_id)->where('id', $request->vacation_type_id)->first();
        if (!$vacationType || !$vacationType->active) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_type_not_found')
            ], 404);
        }

        // Check if attachment is required
        if ($vacationType->requires_attachment && !$request->hasFile('attachment')) {
            return response()->json([
                'status' => false,
                'message' => 'هذا النوع من الإجازات يتطلب إرفاق تقرير طبي أو ملف مبرر'
            ], 422);
        }

        $fromDate = Carbon::parse($request->from_date);
        $toDate = Carbon::parse($request->to_date);
        $daysCount = $fromDate->diffInDays($toDate) + 1;

        // Check date overlap with existing pending or approved requests
        $hasOverlap = VacationRequest::where('company_id', $company_id)
            ->where('employee_id', $employeeId)
            ->whereIn('status', [0, 1]) // Pending or Approved
            ->where(function ($q) use ($request) {
                $q->whereBetween('from_date', [$request->from_date, $request->to_date])
                  ->orWhereBetween('to_date', [$request->from_date, $request->to_date])
                  ->orWhere(function ($sub) use ($request) {
                      $sub->where('from_date', '<=', $request->from_date)
                          ->where('to_date', '>=', $request->to_date);
                  });
            })
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_overlap_error')
            ], 422);
        }

        // Check balance if deduct_from_balance = 1
        $balance = $this->balanceService->calculateForEmployee($employee, $fromDate->year);
        if ($vacationType->deduct_from_balance) {
            if ($balance->remaining_balance < $daysCount) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.vacation_insufficient_balance') . " (الرصيد المتاح: {$balance->remaining_balance} يوم، والمطلوب: {$daysCount} يوم)"
                ], 422);
            }
        }

        // Check max days limit if configured
        if ($vacationType->max_days_per_year > 0 && $daysCount > $vacationType->max_days_per_year) {
            return response()->json([
                'status' => false,
                'message' => "تجاوزت الحد الأقصى المسموح به لهذا النوع من الإجازات ({$vacationType->max_days_per_year} يوم في السنة)"
            ], 422);
        }

        try {
            DB::beginTransaction();

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = 'vacation_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $attachmentPath = $file->storeAs('uploads/vacations', $filename, 'public');
            }

            $vacationRequest = VacationRequest::create([
                'company_id' => $company_id,
                'employee_id' => $employeeId,
                'vacation_type_id' => $vacationType->id,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'days_count' => $daysCount,
                'reason' => $request->reason,
                'attachment_path' => $attachmentPath,
                'status' => 0, // Pending
                'added_by' => $user->id,
            ]);

            // Sync balance (updates pending_balance)
            $this->balanceService->calculateForEmployee($employee, $fromDate->year);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.vacation_request_created'),
                'data' => $vacationRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve vacation request
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $company_id = $user->company_id;

        $vacReq = VacationRequest::where('company_id', $company_id)->where('id', $id)->first();
        if (!$vacReq) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_request_not_found')
            ], 404);
        }

        if ($vacReq->status != 0) {
            return response()->json([
                'status' => false,
                'message' => 'هذا الطلب تمت معالجته مسبقاً'
            ], 422);
        }

        $employee = Employee::find($vacReq->employee_id);
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => __('messages.employee_not_found')
            ], 404);
        }

        try {
            DB::beginTransaction();

            $vacReq->update([
                'status' => 1, // Approved
                'action_by' => $user->id,
                'action_reason' => $request->action_reason ?? 'تم الاعتماد',
                'action_at' => now(),
                'updated_by' => $user->id,
            ]);

            // Sync with Attendances table for each date
            $period = CarbonPeriod::create($vacReq->from_date, $vacReq->to_date);
            foreach ($period as $dt) {
                $dateStr = $dt->format('Y-m-d');
                
                // Find open finance period for this date if exists
                $financePeriod = Finance_months_periods::where('company_id', $company_id)
                    ->where('START_DATE_M', '<=', $dateStr)
                    ->where('END_DATE_M', '>=', $dateStr)
                    ->first();

                $att = Attendance::firstOrNew([
                    'company_id' => $company_id,
                    'employee_code' => $employee->employee_code,
                    'attendance_date' => $dateStr,
                ]);

                $att->finance_months_periods_id = $financePeriod ? $financePeriod->id : ($att->finance_months_periods_id ?? null);
                $att->branch_id = $employee->branch_id;
                $att->attendance_status = 5; // إجازة معتمدة
                $att->vacations_type_id = $vacReq->vacation_type_id;
                $att->absence_hours = 0;
                $att->shift_hours = 0;
                $att->is_processed = 1;
                $att->is_approved = 1;
                $att->actionDateTime = now();
                $att->updated_by = $user->id;
                $att->save();
            }

            // Sync Employee Vacation Balance
            $year = Carbon::parse($vacReq->from_date)->year;
            $this->balanceService->calculateForEmployee($employee, $year);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.vacation_request_approved'),
                'data' => $vacReq
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject vacation request
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        $company_id = $user->company_id;

        $vacReq = VacationRequest::where('company_id', $company_id)->where('id', $id)->first();
        if (!$vacReq) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_request_not_found')
            ], 404);
        }

        if ($vacReq->status != 0) {
            return response()->json([
                'status' => false,
                'message' => 'هذا الطلب تمت معالجته مسبقاً'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $vacReq->update([
                'status' => 2, // Rejected
                'action_by' => $user->id,
                'action_reason' => $request->action_reason ?? 'تم الرفض',
                'action_at' => now(),
                'updated_by' => $user->id,
            ]);

            // Sync Employee Vacation Balance to release pending
            $employee = Employee::find($vacReq->employee_id);
            if ($employee) {
                $year = Carbon::parse($vacReq->from_date)->year;
                $this->balanceService->calculateForEmployee($employee, $year);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.vacation_request_rejected'),
                'data' => $vacReq
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel vacation request (by employee if still pending)
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $company_id = $user->company_id;

        $vacReq = VacationRequest::where('company_id', $company_id)->where('id', $id)->first();
        if (!$vacReq) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_request_not_found')
            ], 404);
        }

        if ($vacReq->status != 0) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_cannot_cancel')
            ], 422);
        }

        try {
            DB::beginTransaction();

            $vacReq->update([
                'status' => 3, // Cancelled
                'updated_by' => $user->id,
            ]);

            // Sync Employee Vacation Balance
            $employee = Employee::find($vacReq->employee_id);
            if ($employee) {
                $year = Carbon::parse($vacReq->from_date)->year;
                $this->balanceService->calculateForEmployee($employee, $year);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.vacation_request_cancelled')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete vacation request
     */
    public function destroy(Request $request, $id)
    {
        $company_id = $request->user()->company_id;

        $vacReq = VacationRequest::where('company_id', $company_id)->where('id', $id)->first();
        if (!$vacReq) {
            return response()->json([
                'status' => false,
                'message' => __('messages.vacation_request_not_found')
            ], 404);
        }

        if ($vacReq->status == 1) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف طلب إجازة معتمد ومربوط بسجلات الحضور'
            ], 422);
        }

        if ($vacReq->attachment_path && Storage::disk('public')->exists($vacReq->attachment_path)) {
            Storage::disk('public')->delete($vacReq->attachment_path);
        }

        $employeeId = $vacReq->employee_id;
        $year = Carbon::parse($vacReq->from_date)->year;

        $vacReq->delete();

        $employee = Employee::find($employeeId);
        if ($employee) {
            $this->balanceService->calculateForEmployee($employee, $year);
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.deleted_successfully')
        ]);
    }
}
