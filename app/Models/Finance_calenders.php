<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finance_calenders extends Model
{
    use HasFactory;
    protected $table = 'finance_calenders';
    protected $fillable = [
        'finance_yr',
        'finance_yr_desc',
        'start_date',
        'end_date',
        'is_open',
        'com_code',
        'added_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    public function added(){
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
}
