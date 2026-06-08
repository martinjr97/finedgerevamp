<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule repayment reminders to run daily at 09:00
Schedule::command('repayments:send-reminders')
    ->dailyAt('09:00')
    ->timezone('Africa/Lusaka')
    ->withoutOverlapping()
    ->runInBackground();
