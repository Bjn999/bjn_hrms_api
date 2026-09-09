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

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function settings()
    {
        return $this->hasOne(CompanySetting::class, 'company_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'company_id')->orderBy('created_at', 'desc');
    }

    public function currentSubscription()
    {
        return $this->hasOne(Subscription::class, 'company_id')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', date('Y-m-d'));
            })
            ->latestOfMany();
    }

    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class, 'company_id')->latestOfMany();
    }

    public function getActiveUsersCountAttribute()
    {
        return $this->users()->where('is_active', 1)->count();
    }
}

