<?php

namespace App\Events;

use App\Models\IotReading;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired the moment a reading transitions from safe to over-threshold.
 * Deliberately NOT fired on every subsequent over-threshold reading
 * while the alert is already active — see IotReadingController, which
 * tracks that transition via a cache flag so residents aren't spammed
 * with a new alert every time the sensor reports.
 */
class ThresholdCrossed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly IotReading $reading,
    ) {}
}
