<?php

namespace App\Models;

use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Center extends TenantModel
{
    use HasFactory;
    protected $table = 'centers';
    protected $guarded = [];

    public function added(){
        return $this->BelongsTo('\App\Models\User', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\User', 'updated_by');
    }
    public function country(){
        return $this->BelongsTo('\App\Models\Country', 'country_id');
    }
    public function governorate(){
        return $this->BelongsTo('\App\Models\Governorate', 'governorate_id');
    }
}
