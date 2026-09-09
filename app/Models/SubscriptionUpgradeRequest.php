<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionUpgradeRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currentPlan()
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function requestedPlan()
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
