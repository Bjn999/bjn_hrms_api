<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee_salary_archive extends Model
{
    use HasFactory;
    
    protected $table = 'employee_salary_archive';
    protected $guarded = [];

    public function added(){
        return $this->BelongsTo(Admins::class, 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo(Admins::class, 'updated_by');
    }
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    public function finance_month(){
        return $this->BelongsTo('\App\Models\Finance_months_periods', 'finance_month_id');
    }
}
