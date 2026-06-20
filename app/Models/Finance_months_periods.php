<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Finance_months_periods extends Model
{
    use HasFactory;
    protected $table = 'finance_months_periods';
    protected $fillable = [
        'finance_calenders_id',
        'number_of_days',
        'year_and_month',
        'finance_yr',
        'month_id',
        'start_date_m',
        'end_date_m',
        'is_open',
        'start_date_for_pasma',
        'end_date_for_pasma',
        'added_by',
        'updated_by',
        'com_code',
        'created_at',
        'updated_at'
    ];

    public function added(){
        return $this->BelongsTo('\App\Models\Admins', 'added_by');
    }
    public function updatedby(){
        return $this->BelongsTo('\App\Models\Admins', 'updated_by');
    }
    public function month(){
        return $this->BelongsTo('\App\Models\Monthes', 'month_id');
    }
}
