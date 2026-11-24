<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Stringable;



// 1. Send reminders at 6 PM Syria time
Schedule::command('clinic:send-reminders')
    ->dailyAt('18:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo(
        storage_path('logs/clinic-reminders.log')
    )
    ->onFailure(function (Stringable $output) {
        Log::error('Scheduled reminders failed', [
            'output' => (string) $output,
        ]);
    });

// 2. Check missed appointments every 5 minutes
Schedule::command('clinic:mark-missed')
    ->everyFiveMinutes()
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(15)
    ->onOneServer()
    ->appendOutputTo(
        storage_path('logs/clinic-missed.log')
    )
    ->onFailure(function (Stringable $output) {
        Log::error('Scheduled missed appointments failed', [
            'output' => (string) $output,
        ]);
    });

// 3. Lift expired bans hourly
Schedule::command('clinic:lift-bans')
    ->hourly()
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(15)
    ->onOneServer()
    ->appendOutputTo(
        storage_path('logs/clinic-bans.log')
    )
    ->onFailure(function (Stringable $output) {
        Log::error('Scheduled ban expiration failed', [
            'output' => (string) $output,
        ]);
    });

// 4. Generate monthly reports on the 1st
Schedule::command('clinic:monthly-report')
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->appendOutputTo(
        storage_path('logs/clinic-reports.log')
    )
    ->onFailure(function (Stringable $output) {
        Log::error('Scheduled monthly report failed', [
            'output' => (string) $output,
        ]);


    });
