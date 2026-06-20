<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Center extends Model
{
    use HasFactory;
    protected $table = 'centers';
    protected $guarded = [];

    public function added(){
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
    public function country(){
        return $this->BelongsTo('\App\Models\Country', 'country_id');
    }
    public function governorate(){
        return $this->BelongsTo('\App\Models\Governorate', 'governorate_id');
    }
}
