<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverAnnouncement;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnnouncementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:status_update,announcement'], // emergency alerts only ever come from TriggerEmergencyOverride
            'target_scope' => ['required', 'in:barangay,purok,municipal_relay'],
            'purok_id' => ['required_if:target_scope,purok', 'nullable', 'exists:puroks,id'],
        ]);

        // While a flood alert is active, non-emergency announcements are
        // deliberately held back so residents' attention stays on the
        // active warning rather than a routine notice. The admin gets a
        // clear reason rather than a silent failure.
        if (Cache::get('flood_alert_active', false)) {
            return response()->json([
                'message' => 'An emergency alert is currently active. This announcement will be held until it clears.',
            ], 409);
        }

        $announcement = Announcement::create([
            ...$validated,
            'sent_by' => $request->user()->id,
        ]);

        DeliverAnnouncement::dispatch($announcement);

        return response()->json(['data' => $announcement], 201);
    }
}
