<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\FloodAlertCleared;
use App\Listeners\NotifyFloodAlertCleared;

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
        Event::listen(FloodAlertCleared::class, NotifyFloodAlertCleared::class);
        //
    }
}
