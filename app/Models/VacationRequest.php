<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class VacationRequest extends TenantModel
{
    use HasFactory;

    protected $table = 'vacation_requests';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function vacationType()
    {
        return $this->belongsTo(VacationType::class, 'vacation_type_id');
    }

    public function actionByUser()
    {
        return $this->belongsTo(User::class, 'action_by');
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
