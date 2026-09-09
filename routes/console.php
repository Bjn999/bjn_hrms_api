<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription expiration & warning check to run continuously in background (hourly evaluation for each company's timezone)
Schedule::command('subscriptions:check-expirations')->hourly()->withoutOverlapping();

