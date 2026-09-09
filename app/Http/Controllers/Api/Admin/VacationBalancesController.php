<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeVacationBalance;
use App\Services\VacationBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VacationBalancesController extends Controller
{
    protected $balanceService;

    public function __construct(VacationBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    public function index(Request $request)
    {
        $company_id = $request->user()->company_id;
        $year = (int)($request->year ?? date('Y'));

        // First, ensure all active employees have their balance synced for the requested year
        $employees = Employee::where('company_id', $company_id)
            ->where('functional_status', 1)
            ->with(['department:id,name', 'job:id,name'])
            ->get();

        foreach ($employees as $employee) {
            $this->balanceService->calculateForEmployee($employee, $year);
        }

        $query = EmployeeVacationBalance::where('company_id', $company_id)
            ->where('year', $year)
            ->with(['employee' => function ($q) {
                $q->select('id', 'emp_name', 'employee_code', 'emp_start_date', 'emp_departments_id', 'emp_job_id', 'is_active_for_vaccation')
                  ->with(['department:id,name', 'job:id,name']);
            }]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $data = $query->orderBy('id', 'DESC')->get();

        return response()->json([
            'status' => true,
            'year' => $year,
            'data' => $data
        ]);
    }

    public function recalculate(Request $request)
    {
        $company_id = $request->user()->company_id;
        $year = (int)($request->year ?? date('Y'));

        $count = $this->balanceService->recalculateCompany($company_id, $year);

        return response()->json([
            'status' => true,
            'message' => __('messages.vacation_balances_recalculated'),
            'count' => $count
        ]);
    }

    public function update(Request $request, $id)
    {
        $company_id = $request->user()->company_id;

        $balance = EmployeeVacationBalance::where('company_id', $company_id)->where('id', $id)->first();
        if (!$balance) {
            return response()->json([
                'status' => false,
                'message' => __('messages.not_found')
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'carried_over_balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->has('carried_over_balance')) {
                $balance->carried_over_balance = $request->carried_over_balance;
            }
            if ($request->has('notes')) {
                $balance->notes = $request->notes;
            }
            $balance->updated_by = $request->user()->id;
            $balance->save();

            // Recalculate total and remaining
            $employee = Employee::find($balance->employee_id);
            if ($employee) {
                $balance = $this->balanceService->calculateForEmployee($employee, $balance->year);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.vacation_balance_updated'),
                'data' => $balance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }
}
