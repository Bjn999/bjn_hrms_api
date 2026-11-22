<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Main_salary_employee_discount extends TenantModel
{
    use HasFactory;
    protected $table = "main_salary_employee_discounts";
    protected $guarded = [];
    
    public function added(){
        return $this->BelongsTo('\App\Models\User', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\User', 'updated_by');
    }
    public function employee(){
        return $this->BelongsTo('\App\Models\Employee', 'employee_code', 'employee_code');
    }
    public function archived_by(){
        return $this->BelongsTo('\App\Models\User', 'archived_by');
    }
    public function discount_type(){
        return $this->BelongsTo('\App\Models\discount_sal_type', 'discounts_type_id');
    }
    public function finance_month(){
        return $this->BelongsTo('\App\Models\Finance_months_periods', 'finance_month_periods_id');
    }
}
