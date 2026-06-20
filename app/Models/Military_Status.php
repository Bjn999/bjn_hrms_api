<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Military_Status extends Model
{
    use HasFactory;
    protected $table = 'military_statuses';
    protected $guarded = [];
}
