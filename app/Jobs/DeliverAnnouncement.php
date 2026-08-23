<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Services\ReportNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Resolves the recipient list for an Announcement (emergency alert,
 * status update, or tiered community notice), writes the in-app
 * notification for each resident, and fans out individual
 * SendPushNotification jobs to whoever has a registered device. Shared
 * by both the IoT Emergency Override path and the admin-facing tiered
 * announcement composer — the only difference between them is how the
 * Announcement got created.
 */
class DeliverAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly Announcement $announcement,
    ) {}

    public function handle(ReportNotificationService $notifications): void
    {
        $this->residentsInScope()->chunk(100, function ($residents) use ($notifications): void {
            foreach ($residents as $resident) {
                // Every resident in scope gets the in-app notification,
                // regardless of whether they've registered a device —
                // that's what makes it "in-app" rather than push-only.
                $notifications->send(
                    $resident,
                    $this->announcement->title,
                    $this->announcement->message,
                    null,
                    $this->announcement->type,
                );

                if ($resident->fcm_token !== null) {
                    SendPushNotification::dispatch(
                        $resident,
                        title: $this->announcement->title,
                        body: $this->announcement->message,
                    );
                }
            }
        });
    }

    private function residentsInScope(): Builder
    {
        $query = User::query()->where('role', 'resident');

        return match ($this->announcement->target_scope) {
            // "barangay" and "municipal_relay" both go to every resident —
            // a relayed municipal program still needs to reach everyone.
            'barangay', 'municipal_relay' => $query,
            'purok' => $query->where('purok_id', $this->announcement->purok_id),
            default => $query->whereRaw('1 = 0'), // unknown scope: send to no one rather than guess
        };
    }

    public function failed(Throwable $e): void
    {
        logger()->error('DeliverAnnouncement failed', [
            'announcement_id' => $this->announcement->id,
            'error' => $e->getMessage(),
        ]);
    }
}
