<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Driving_license_type extends TenantModel
{
    use HasFactory;
    protected $table = 'driving_license_types';
    protected $guarded = [];

    public function added(){
        return $this->BelongsTo('\App\Models\User', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\User', 'updated_by');
    }
}
