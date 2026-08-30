<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee_File extends TenantModel
{
    use HasFactory;
    protected $table = "employees_files";
    protected $guarded = [];
    
    public function employee(){
        return $this->BelongsTo('\App\Models\Employee', 'employee_id');
    }
    public function added(){
        return $this->BelongsTo('\App\Models\User', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\User', 'updated_by');
    }
}
