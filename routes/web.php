<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AnnouncementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Admin dashboard
|--------------------------------------------------------------------------
| Session-authenticated via the 'auth' middleware, which ships with the
| framework already — but there's no login flow wired up yet. Add one
| (Laravel Breeze is the fastest path: composer require laravel/breeze
| --dev && php artisan breeze:install) before these routes are reachable.
*/
Route::middleware('auth')->prefix('admin')->group(function (): void {
    Route::get('/analytics/puroks', [AnalyticsController::class, 'purokRanking']);
    Route::get('/analytics/heatmap', [AnalyticsController::class, 'heatmap']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
});
