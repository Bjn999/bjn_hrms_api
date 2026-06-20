<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resignation extends Model
{
    use HasFactory;
    protected $table = 'resignations';
    protected $guarded = [];
    
    public function added(){
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
}
