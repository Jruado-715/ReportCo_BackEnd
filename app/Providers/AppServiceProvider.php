<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Event listeners are auto-discovered from app/Listeners (Laravel's
        // default). NotifyFloodAlertCleared and TriggerEmergencyOverride are
        // both picked up that way — registering NotifyFloodAlertCleared
        // manually here as well made it fire twice, so residents received
        // two "flood alert cleared" notifications (in-app and push) per
        // event. The manual registration has been removed.
    }
}
