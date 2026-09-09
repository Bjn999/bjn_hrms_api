<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeVacationBalance extends TenantModel
{
    use HasFactory;

    protected $table = 'employee_vacation_balances';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = [
        'carried_over_balance' => 0.0,
        'initial_assigned_balance' => 0.0,
        'monthly_accrued_balance' => 0.0,
        'total_entitled_balance' => 0.0,
        'used_balance' => 0.0,
        'pending_balance' => 0.0,
        'remaining_balance' => 0.0,
    ];

    protected $casts = [
        'carried_over_balance' => 'float',
        'initial_assigned_balance' => 'float',
        'monthly_accrued_balance' => 'float',
        'total_entitled_balance' => 'float',
        'used_balance' => 'float',
        'pending_balance' => 'float',
        'remaining_balance' => 'float',
        'last_calculated_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
