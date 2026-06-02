<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:process')->dailyAt('09:00');

// Nightly deep sync: pull fresh customers + 30 days of bills from ERP at 2am
Schedule::command('bms:sync-bills --days=30')->dailyAt('02:00')->withoutOverlapping()->runInBackground();

// Hourly incremental sync: keep today's and yesterday's bills fresh so dashboard updates gradually
Schedule::command('bms:sync-bills --days=2')->hourly()->withoutOverlapping()->runInBackground();

// Sync the 50,000+ record ERP payment statuses API every hour
Schedule::command('erp:sync-statuses')->hourly()->withoutOverlapping()->runInBackground();
