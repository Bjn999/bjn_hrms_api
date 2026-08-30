<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceAction extends TenantModel
{
    use HasFactory;

    protected $table = 'attendance_actions';

    protected $fillable = [
        'company_id',
        'finance_months_periods_id',
        'attendance_id',
        'employee_code',
        'actionDateTime',
        'actionType',
        'actionMethod',
        'it_is_active_with_parent',
        'is_processed',
        'added_by',
        'updated_by'
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function financePeriod()
    {
        return $this->belongsTo(Finance_months_periods::class, 'finance_months_periods_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'employee_code');
    }

    public function addedByAdmin()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function updatedByAdmin()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
