<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the Python ML microservice (FastAPI + scikit-learn)
 * that hosts the code-switched SVM classifier and the K-Means clustering
 * endpoint. Laravel never runs scikit-learn itself — it just calls out to
 * this service over HTTP, from a queued job so requests stay fast.
 *
 * Add to config/services.php:
 *   'ml_classifier' => [
 *       'base_url' => env('ML_CLASSIFIER_URL', 'http://127.0.0.1:8001'),
 *       'timeout'  => env('ML_CLASSIFIER_TIMEOUT', 5),
 *   ],
 */
class MlClassifierService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ml_classifier.base_url'), '/');
        $this->timeout = (int) config('services.ml_classifier.timeout', 5);
    }

    /**
     * Classify a single code-switched complaint description.
     *
     * @return array{category: string, confidence: float}
     */
    public function classify(string $text): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->retry(2, 200)
            ->post('/classify', ['text' => $text]);

        if ($response->failed()) {
            throw new RuntimeException(
                "ML classifier request failed: {$response->status()} {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Run K-Means over a batch of geo-tagged report coordinates and get
     * back cluster assignments for the admin heatmap.
     *
     * @param  array<int, array{id: int, lat: float, lng: float}>  $points
     * @return array{clusters: array<int, array{center_lat: float, center_lng: float, report_ids: array<int>}>}
     */
    public function cluster(array $points): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout * 2) // clustering the full dataset takes longer than a single classify call
            ->post('/cluster', ['points' => $points]);

        if ($response->failed()) {
            throw new RuntimeException(
                "ML clustering request failed: {$response->status()} {$response->body()}"
            );
        }

        return $response->json();
    }
}
