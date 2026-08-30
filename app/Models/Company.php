<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function settings()
    {
        return $this->hasOne(CompanySetting::class, 'company_id');
    }
}
