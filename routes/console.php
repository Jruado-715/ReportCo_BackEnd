<?php

use App\Jobs\RefreshClusters;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recomputes the K-Means heatmap for the admin dashboard. Requires the
// scheduler to actually be running — see the "dev" composer script
// (php artisan queue:listen is already there; add
// `php artisan schedule:work` alongside it locally, or a real cron entry
// calling `php artisan schedule:run` every minute in production).
Schedule::job(new RefreshClusters)->everyFifteenMinutes();
