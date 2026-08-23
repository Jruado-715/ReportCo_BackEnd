<?php

namespace App\Listeners;

use App\Events\FloodAlertCleared;
use App\Jobs\SendPushNotification;
use App\Models\User;
use App\Services\ReportNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Throwable;

class NotifyFloodAlertCleared implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function handle(FloodAlertCleared $event): void
    {
        $recipients = User::query()->where('role', 'resident');
        $purokId = config('services.flood_sensor.purok_id');
        if ($purokId) {
            $recipients->where('purok_id', $purokId);
        }

        $recipients->chunk(100, function ($users): void {
            foreach ($users as $user) {
                app(ReportNotificationService::class)->send(
                    $user,
                    'Flood alert cleared',
                    'Water level has returned below the configured safety threshold. Continue following barangay instructions.',
                    null,
                    'flood_cleared'
                );
                SendPushNotification::dispatch(
                    $user,
                    'Flood alert cleared',
                    'Water level has returned below the configured safety threshold.'
                );
            }
        });
    }

    public function failed(FloodAlertCleared $event, Throwable $e): void
    {
        logger()->error('NotifyFloodAlertCleared failed', [
            'reading_id' => $event->reading->id,
            'error' => $e->getMessage(),
        ]);
    }
}
