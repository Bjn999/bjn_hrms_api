<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee_fixed_allowance extends Model
{
    use HasFactory;
    protected $table = "employee_fixed_allowance";
    protected $guarded = [];

    public function employee(){
        return $this->BelongsTo('\App\Models\Employee', 'employee_id');
    }
    public function allowance(){
        return $this->BelongsTo('\App\Models\Allowance', 'allowance_id');
    }
    public function added(){
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
}
