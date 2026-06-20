<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Main_salary_employee_allowance extends Model
{
    use HasFactory;
    protected $table = "main_salary_employee_allowances";
    protected $guarded = [];
    
    public function added(){
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
    public function employee(){
        return $this->BelongsTo('\App\Models\Employee', 'employee_code');
    }
    public function archived_by(){
        return $this->BelongsTo('\App\Models\Admins', 'archived_by');
    }
    public function allowance(){
        return $this->BelongsTo('\App\Models\Allowance', 'allowances_id');
    }
    public function finance_month(){
        return $this->BelongsTo('\App\Models\Finance_months_periods', 'finance_months_periods_id');
    }
}
