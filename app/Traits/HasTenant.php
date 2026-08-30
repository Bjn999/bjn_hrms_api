<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * @method static void addGlobalScope(\Illuminate\Database\Eloquent\Scope $scope)
 * @method static void creating(callable $callback)
 */
trait HasTenant
{
    /**
     * Boot the trait and apply the tenant scope.
     *
     * @return void
     */
    protected static function bootHasTenant()
    {
        // Apply the global scope to filter queries by company_id
        static::addGlobalScope(new TenantScope);

        // Hook into the creating event to automatically set the company_id
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->role !== 'super_admin' && !$model->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}
