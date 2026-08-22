<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\MlClassifierService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs on Laravel's scheduler (see routes/console.php) rather than after
 * every report submission — K-Means over the whole dataset is too
 * expensive to run per-request, so the admin heatmap is "fresh as of the
 * last refresh." The cache TTL is set slightly longer than the refresh
 * interval so the dashboard never shows a hard "no data" gap between runs.
 */
class RefreshClusters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60; // clustering the full dataset can take longer than a single classify call

    public const CACHE_KEY = 'report_heatmap_clusters';
    private const CACHE_TTL_MINUTES = 20;

    public function handle(MlClassifierService $classifier): void
    {
        $points = Report::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'latitude', 'longitude'])
            ->map(fn (Report $report): array => [
                'id' => $report->id,
                'lat' => (float) $report->latitude,
                'lng' => (float) $report->longitude,
            ])
            ->all();

        if ($points === []) {
            Cache::put(self::CACHE_KEY, [], now()->addMinutes(self::CACHE_TTL_MINUTES));
            return;
        }

        $result = $classifier->cluster($points);

        Cache::put(self::CACHE_KEY, $result['clusters'], now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    public function failed(Throwable $e): void
    {
        // Deliberately does NOT clear the cache — a failed refresh should
        // leave the last-known-good heatmap on the dashboard rather than
        // blanking it out.
        logger()->error('RefreshClusters failed', ['error' => $e->getMessage()]);
    }
}
