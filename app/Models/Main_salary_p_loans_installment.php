<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Main_salary_employee_permanent_loan;

class Main_salary_p_loans_installment extends Model
{
    use HasFactory;
    protected $table = "main_salary_p_loans_installments";
    protected $guarded = [];

    public function added()
    {
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby()
    {
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
    public function archived_by()
    {
        return $this->BelongsTo('\App\Models\Admins', 'archived_by');
    }
    public function parent_loan()
    {
        return $this->BelongsTo(Main_salary_employee_permanent_loan::class, 'main_salary_p_loans_id');
    }
    public function employee_sal_record()
    {
        return $this->BelongsTo('\App\Models\Main_salary_employee', 'main_salary_employee_id');
    }
}
