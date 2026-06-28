<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('contracts:expire')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('reviews:send-reminders')->daily();
Schedule::command('contracts:send-expiry-reminders')->daily();
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
