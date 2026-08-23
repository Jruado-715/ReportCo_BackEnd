<?php

namespace App\Listeners;

use App\Events\ThresholdCrossed;
use App\Jobs\DeliverAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * This is the Emergency Override itself. It does NOT touch the SVM
 * classifier or the Guided Resolution queue at all — a sensor reading
 * never originated as a resident report, so there is nothing to
 * classify. It goes straight to the notification layer as a
 * top-priority alert, bypassing the ordinary report pipeline entirely.
 *
 * Auto-discovered by Laravel — no manual registration needed as long as
 * handle() is typed against the event, which it is below.
 */
class TriggerEmergencyOverride implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 30;

    public function handle(ThresholdCrossed $event): void
    {
        $senderId = config('services.flood_sensor.system_user_id') ?: User::where('role', 'system_admin')->value('id');
        if (! $senderId) {
            logger()->error('Cannot create flood announcement: no system admin sender configured.');
            return;
        }

        $announcement = Announcement::create([
            // A seeded system/service account, not a human admin — see
            // config('services.flood_sensor.system_user_id'). Simpler
            // than making `sent_by` nullable just for this one case.
            'sent_by' => $senderId,
            'purok_id' => config('services.flood_sensor.purok_id'), // null = whole barangay
            'title' => 'Flood warning: evacuate low-lying areas now',
            'message' => sprintf(
                'Water level at the barangay sensor reached %.1f cm, above the %.1f cm safety threshold. Please move to higher ground and follow barangay instructions.',
                (float) $event->reading->water_level,
                (float) config('services.flood_sensor.threshold_cm'),
            ),
            'type' => 'emergency',
            'target_scope' => config('services.flood_sensor.purok_id') ? 'purok' : 'barangay',
        ]);

        DeliverAnnouncement::dispatch($announcement);
    }

    public function failed(ThresholdCrossed $event, Throwable $e): void
    {
        logger()->error('TriggerEmergencyOverride failed', [
            'reading_id' => $event->reading->id,
            'water_level' => $event->reading->water_level,
            'error' => $e->getMessage(),
        ]);
    }
}
