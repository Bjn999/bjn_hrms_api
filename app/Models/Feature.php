<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function planFeatures()
    {
        return $this->hasMany(PlanFeature::class);
    }
}
