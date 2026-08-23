<?php

namespace App\Services;

use App\Exceptions\FcmInvalidTokenException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmService
{
    // FCM v1 error codes that mean "this specific token is dead" —
    // see https://firebase.google.com/docs/reference/fcm/rest/v1/ErrorCode
    private const INVALID_TOKEN_ERROR_CODES = ['UNREGISTERED', 'INVALID_ARGUMENT'];

    public function sendToToken(string $token, string $title, string $body): void
    {
        $projectId = (string) config('services.fcm.project_id');
        $clientEmail = (string) config('services.fcm.client_email');
        $privateKey = (string) config('services.fcm.private_key');

        if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
            Log::warning('FCM delivery skipped: FCM service-account settings are not configured.', ['title' => $title]);
            return;
        }

        $accessToken = $this->accessToken($clientEmail, $privateKey);
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                ],
            ]);

        if ($response->failed()) {
            if ($this->isInvalidTokenError($response->json())) {
                throw new FcmInvalidTokenException(
                    'FCM reported this device token as invalid or unregistered: '.$response->body()
                );
            }

            throw new RuntimeException('FCM delivery failed: '.$response->status().' '.$response->body());
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function isInvalidTokenError(?array $body): bool
    {
        $status = $body['error']['status'] ?? null;
        if ($status === 'NOT_FOUND') {
            return true;
        }

        $details = $body['error']['details'] ?? [];
        foreach ($details as $detail) {
            if (in_array($detail['errorCode'] ?? null, self::INVALID_TOKEN_ERROR_CODES, true)) {
                return true;
            }
        }

        return false;
    }

    private function accessToken(string $clientEmail, string $privateKey): string
    {
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;
        if (! openssl_sign($unsigned, $signature, str_replace("\\n", "\n", $privateKey), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the FCM service-account assertion.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.$this->base64Url($signature),
        ]);
        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('Unable to obtain an FCM access token: '.$response->status().' '.$response->body());
        }
        return (string) $response->json('access_token');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
