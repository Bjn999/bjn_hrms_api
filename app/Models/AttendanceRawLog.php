<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceRawLog extends TenantModel
{
    use HasFactory;

    protected $table = 'attendance_raw_logs';

    protected $fillable = [
        'company_id',
        'finance_months_periods_id',
        'employee_code',
        'actionDateTime',
        'actionType',
        'actionMethod',
        'main_salary_employee_id',
        'added_by'
    ];

    public function financePeriod()
    {
        return $this->belongsTo(Finance_months_periods::class, 'finance_months_periods_id');
    }

    public function mainSalaryEmployee()
    {
        return $this->belongsTo(Main_salary_employee::class, 'main_salary_employee_id');
    }

    public function addedByAdmin()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'employee_code');
    }
}
