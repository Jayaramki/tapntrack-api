<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily trial-expiry sweep. Requires a cPanel cron running every minute:
//   php /home/tapntrac/public_html/api.tapntrack.in/artisan schedule:run
Schedule::command('tenants:expire-trials')->dailyAt('00:30');
