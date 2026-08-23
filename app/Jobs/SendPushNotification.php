<?php

namespace App\Jobs;

use App\Exceptions\FcmInvalidTokenException;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(
        private readonly User $recipient,
        private readonly string $title,
        private readonly string $body,
    ) {}

    public function handle(FcmService $fcm): void
    {
        if ($this->recipient->fcm_token === null) {
            return; // resident hasn't registered a device yet
        }

        try {
            $fcm->sendToToken($this->recipient->fcm_token, $this->title, $this->body);
        } catch (FcmInvalidTokenException $e) {
            // The token itself is dead — retrying won't help, and leaving
            // it in place means every future alert fails the same way.
            // Clear it so the resident just falls back to in-app-only
            // notifications until they open the app and re-register.
            $this->recipient->forceFill(['fcm_token' => null])->save();

            logger()->info('Cleared invalid FCM token', [
                'user_id' => $this->recipient->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        logger()->error('SendPushNotification failed', [
            'user_id' => $this->recipient->id,
            'error' => $e->getMessage(),
        ]);
    }
}
