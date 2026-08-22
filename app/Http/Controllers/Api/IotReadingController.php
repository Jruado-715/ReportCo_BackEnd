<?php

namespace App\Http\Controllers\Api;

use App\Events\FloodAlertCleared;
use App\Events\ThresholdCrossed;
use App\Http\Controllers\Controller;
use App\Models\IotReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The ESP32 posts here on every reading cycle. Route is protected by
 * the `device` middleware (VerifyDeviceToken) — a shared key, not a
 * resident/admin Sanctum session.
 */
class IotReadingController extends Controller
{
    private const CACHE_KEY = 'flood_alert_active';

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'water_level' => ['required', 'numeric', 'between:0,1000'],
        ]);

        $threshold = (float) config('services.flood_sensor.threshold_cm');
        $crossed = (float) $validated['water_level'] >= $threshold;

        $reading = IotReading::create([
            'water_level' => $validated['water_level'],
            'threshold_crossed' => $crossed,
            'recorded_at' => now(),
        ]);

        $this->handleTransition($reading, $crossed);

        return response()->json(['data' => $reading], 201);
    }

    /**
     * Only fires an event on the safe<->unsafe transition, not on every
     * reading — otherwise a sensor reporting every 30 seconds during a
     * prolonged flood would fire a fresh emergency alert every 30 seconds.
     */
    private function handleTransition(IotReading $reading, bool $crossed): void
    {
        if ($crossed) {
            // Cache::add is atomic for supported cache stores: only the first
            // concurrent threshold-crossing request becomes the transition.
            if (Cache::add(self::CACHE_KEY, true, now()->addYears(10))) {
                event(new ThresholdCrossed($reading));
            }
            return;
        }

        $wasActive = Cache::get(self::CACHE_KEY, false);
        if (! $crossed && $wasActive) {
            Cache::forget(self::CACHE_KEY);
            event(new FloodAlertCleared($reading));
        }
    }
}
