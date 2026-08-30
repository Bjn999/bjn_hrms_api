<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attendance extends TenantModel
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'company_id',
        'employee_code',
        'finance_months_periods_id',
        'main_salary_employee_id',
        'attendance_date',
        'variables',
        'shift_hours',
        'attendance_status',
        'total_hours',
        'check_in',
        'check_out',
        'late_attendance',
        'early_leave',
        'overtime_hours',
        'absence_hours',
        'ezen_hours',
        'is_processed',
        'vacations_type_id',
        'occasions_id',
        'cut',
        'year_and_month',
        'branch_id',
        'functional_status',
        'actionDateTime',
        'is_approved',
        'is_archived',
        'archived_at',
        'archived_by',
        'added_by',
        'updated_by'
    ];

    public function employee()
    {
        // الربط عبر كود الموظف الفريد
        return $this->belongsTo(Employee::class, 'employee_code', 'employee_code');
    }

    public function financePeriod()
    {
        return $this->belongsTo(Finance_months_periods::class, 'finance_months_periods_id');
    }

    public function mainSalaryEmployee()
    {
        return $this->belongsTo(Main_salary_employee::class, 'main_salary_employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branche::class, 'branch_id');
    }

    public function addedByAdmin()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function updatedByAdmin()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function archivedByAdmin()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}

