<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branche extends TenantModel
{
    use HasFactory;
    protected $table = 'branches';
    protected $fillable = [
        'name',
        'address',
        'phones',
        'email',
        'active',
        'latitude',
        'longitude',
        'geofence_radius',
        'added_by',
        'updated_by',
        'company_id',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'geofence_radius' => 'integer',
        'active' => 'integer',
    ];
    
    public function added(){
        return $this->BelongsTo('\App\Models\User', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\User', 'updated_by');
    }
}
