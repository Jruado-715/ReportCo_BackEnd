<?php

namespace App\Events;

use App\Models\IotReading;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FloodAlertCleared
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly IotReading $reading) {}
}
