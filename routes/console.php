<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:process')->dailyAt('09:00');

// Nightly sync: pull fresh customers + bills from ERP at 2am
Schedule::command('bms:sync-bills')->dailyAt('02:00')->runInBackground();

