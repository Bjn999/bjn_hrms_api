<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Monthes extends Model
{
    use HasFactory;
    protected $table = 'months';
    protected $fillable = [
        'name',
        'name_en'
    ];
}
