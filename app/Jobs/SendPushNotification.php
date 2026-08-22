<?php

namespace App\Jobs;

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

        $fcm->sendToToken($this->recipient->fcm_token, $this->title, $this->body);
    }

    public function failed(Throwable $e): void
    {
        logger()->error('SendPushNotification failed', [
            'user_id' => $this->recipient->id,
            'error' => $e->getMessage(),
        ]);
    }
}
