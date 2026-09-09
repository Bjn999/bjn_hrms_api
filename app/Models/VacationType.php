<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class VacationType extends TenantModel
{
    use HasFactory;

    protected $table = 'vacation_types';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function requests()
    {
        return $this->hasMany(VacationRequest::class, 'vacation_type_id');
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
