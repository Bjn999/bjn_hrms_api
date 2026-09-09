<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Company Admin has implicit full access to all company permissions within their company
        Gate::before(function ($user, $ability) {
            if ($user->role === 'company_admin') {
                return true;
            }
            return null;
        });
    }
}
