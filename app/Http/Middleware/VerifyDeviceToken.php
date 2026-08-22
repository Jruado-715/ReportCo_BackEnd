<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight auth for the single water-level sensor — a static shared
 * key sent in a header, checked against config('services.flood_sensor.device_key').
 * Full Sanctum tokens are overkill for one fixed ESP32 device; residents
 * and admins still go through Sanctum on their own routes.
 *
 * Add to .env: FLOOD_SENSOR_DEVICE_KEY=<a long random string>
 */
class VerifyDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.flood_sensor.device_key');
        $provided = $request->header('X-Device-Key');

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid or missing device key.');
        }

        return $next($request);
    }
}
