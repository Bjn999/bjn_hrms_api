<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Main_salary_p_loans_installment;
// use Laravel\Scout\Searchable;

class Main_salary_employee_permanent_loan extends Model
{
    use HasFactory;
    protected $table = "main_salary_employee_permanent_loans";
    protected $guarded = [];

    public function added()
    {
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby()
    {
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
    public function employee()
    {
        return $this->BelongsTo('\App\Models\Employee', 'employee_code');
    }
    public function archived_by()
    {
        return $this->BelongsTo('\App\Models\Admins', 'archived_by');
    }

    public function installments()
    {
        return $this->hasMany(Main_salary_p_loans_installment::class, 'main_salary_p_loans_id');
    }

    //
    // public function toSearchableArray() {
    //     return [
    //         'total' => $this->total,
    //         'monthly_installment_value' => $this->monthly_installment_value,
    //         'months_number' => $this->months_number,
    //         'year_and_month_start_date' => $this->year_and_month_start_date,
    //         'total_paid' => $this->total_paid,
    //         'total_remain' => $this->total_remain,
    //         'notes' => $this->notes,
    //     ];
    // }
}
