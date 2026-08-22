<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    public function reports(Request $request): JsonResponse
    {
        $q = Report::query()
            ->with('purok:id,name', 'street:id,purok_id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest();

        foreach (['purok_id', 'street_id', 'category', 'priority', 'status'] as $field) {
            if ($request->filled($field)) {
                $q->where($field, $request->input($field));
            }
        }

        $reports = $q->limit(500)->get([
            'id', 'purok_id', 'street_id', 'description', 'latitude', 'longitude',
            'category', 'priority', 'status', 'resident_urgency',
            'emergency_override', 'created_at',
        ]);

        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function reverseGeocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // This endpoint is intentionally user-triggered (e.g. "Identify this location")
        // and should not be used for autocomplete/bulk geocoding.
        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'ReportCo/1.0 (community reporting platform)',
                'Accept-Language' => 'en',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $data['latitude'],
                'lon' => $data['longitude'],
                'format' => 'jsonv2',
                'zoom' => 18,
                'addressdetails' => 1,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Map address lookup is temporarily unavailable.',
            ], 502);
        }

        $json = $response->json();

        return response()->json([
            'success' => true,
            'data' => [
                'display_name' => $json['display_name'] ?? null,
                'address' => $json['address'] ?? [],
                'latitude' => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
            ],
        ]);
    }
    public function mankilamBoundary(): JsonResponse
    {
        $data = cache()->remember('reportco:mankilam-boundary:v1', now()->addDay(), function () {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'ReportCo/1.0 (Mankilam community reporting platform)',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => 'Mankilam, Tagum City, Davao del Norte, Philippines',
                    'format' => 'jsonv2',
                    'limit' => 10,
                    'polygon_geojson' => 1,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            foreach ($response->json() as $place) {
                $address = $place['address'] ?? [];
                $name = strtolower((string) ($place['name'] ?? ''));
                $display = strtolower((string) ($place['display_name'] ?? ''));
                $isMankilam = $name === 'mankilam' || str_contains($display, 'mankilam');
                $isTagum = str_contains(strtolower((string) ($address['city'] ?? $address['municipality'] ?? '')), 'tagum');
                $geometry = $place['geojson'] ?? null;

                if ($isMankilam && $isTagum && $geometry && in_array($geometry['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
                    return [
                        'type' => 'Feature',
                        'properties' => ['name' => 'Barangay Mankilam'],
                        'geometry' => $geometry,
                    ];
                }
            }

            return null;
        });

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'Mankilam boundary is temporarily unavailable.',
            ], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

}
