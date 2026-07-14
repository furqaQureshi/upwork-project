<?php

namespace App\Services\WebPush;

use App\Models\PushDeliveryLog;
use App\Models\PushSubscription as UserPushSubscription;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function sendToUser(User $user, array $payload): void
    {
        $subscriptions = $user->pushSubscriptions()
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $fcmSubscriptions = $subscriptions->filter(fn (UserPushSubscription $subscription): bool => $subscription->provider === 'fcm');
        $legacySubscriptions = $subscriptions->reject(fn (UserPushSubscription $subscription): bool => $subscription->provider === 'fcm');

        if ($fcmSubscriptions->isNotEmpty()) {
            $this->sendFcmNotifications($fcmSubscriptions, $payload);
        }

        if ($legacySubscriptions->isNotEmpty()) {
            $this->sendLegacyWebPush($legacySubscriptions, $payload);
        }
    }

    private function sendLegacyWebPush(Collection $subscriptions, array $payload): void
    {
        if (! class_exists(WebPush::class) || ! $this->hasValidVapidConfiguration()) {
            return;
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (! is_string($payloadJson) || $payloadJson === '') {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ], [
            'TTL' => (int) config('webpush.ttl', 43200),
        ]);

        $subscriptionsByEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $subscriptionsByEndpoint[$subscription->endpoint] = $subscription;

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
                ]),
                $payloadJson
            );
        }

        try {
            foreach ($webPush->flush() as $report) {
                $endpoint = (string) $report->getRequest()->getUri();
                $subscription = $subscriptionsByEndpoint[$endpoint] ?? null;

                if (! $subscription) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $subscription->update([
                        'is_active' => true,
                        'last_used_at' => now(),
                    ]);

                    continue;
                }

                $statusCode = $report->getResponse()?->getStatusCode();

                if ($report->isSubscriptionExpired() || in_array($statusCode, [404, 410], true)) {
                    $subscription->update([
                        'is_active' => false,
                        'last_used_at' => now(),
                    ]);
                }
            }
        } catch (Throwable) {
            // Ignore transient push transport failures.
        }
    }

    private function sendFcmNotifications(Collection $subscriptions, array $payload): void
    {
        if (! $this->hasValidFcmConfiguration()) {
            foreach ($subscriptions as $subscription) {
                $this->logFcmDelivery(
                    $subscription,
                    $payload,
                    'failure',
                    null,
                    null,
                    'FCM_CONFIG_MISSING',
                    'FCM configuration is missing. Configure credentials from admin settings or env.'
                );
            }

            return;
        }

        $accessToken = $this->getFcmAccessToken();
        $projectId = trim($this->resolveFcmConfigValue('fcm_project_id', 'services.fcm.project_id'));

        if (! $accessToken || $projectId === '') {
            foreach ($subscriptions as $subscription) {
                $this->logFcmDelivery(
                    $subscription,
                    $payload,
                    'failure',
                    null,
                    null,
                    'FCM_AUTH_FAILED',
                    'Unable to get FCM access token. Check service account credentials.'
                );
            }

            return;
        }

        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

        foreach ($subscriptions as $subscription) {
            $token = trim((string) ($subscription->resolved_token ?? ''));

            if ($token === '') {
                $this->logFcmDelivery(
                    $subscription,
                    $payload,
                    'failure',
                    null,
                    null,
                    'MISSING_DEVICE_TOKEN',
                    'FCM device token is missing for this subscription.'
                );

                continue;
            }

            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->timeout(20)
                    ->post($endpoint, $this->buildFcmMessage($token, $payload));

                if ($response->successful()) {
                    $subscription->update([
                        'is_active' => true,
                        'last_used_at' => now(),
                    ]);

                    $this->logFcmDelivery(
                        $subscription,
                        $payload,
                        'success',
                        $response->status(),
                        $this->responseSnapshot($response)
                    );

                    continue;
                }

                if ($response->status() === 401) {
                    Cache::forget($this->fcmAccessTokenCacheKey());
                }

                $responseBody = strtoupper($response->body());
                $responseSnapshot = $this->responseSnapshot($response);
                $errorCode = data_get($responseSnapshot, 'error.status');
                $errorMessage = data_get($responseSnapshot, 'error.message');

                if (in_array($response->status(), [404, 410], true)
                    || str_contains($responseBody, 'UNREGISTERED')
                    || str_contains($responseBody, 'REGISTRATION-TOKEN-NOT-REGISTERED')) {
                    $subscription->update([
                        'is_active' => false,
                        'last_used_at' => now(),
                    ]);
                }

                $this->logFcmDelivery(
                    $subscription,
                    $payload,
                    'failure',
                    $response->status(),
                    $responseSnapshot,
                    is_string($errorCode) && $errorCode !== '' ? $errorCode : 'FCM_SEND_FAILED',
                    is_string($errorMessage) && $errorMessage !== '' ? $errorMessage : Str::limit($response->body(), 1000)
                );
            } catch (Throwable $exception) {
                $this->logFcmDelivery(
                    $subscription,
                    $payload,
                    'failure',
                    null,
                    null,
                    'FCM_TRANSPORT_EXCEPTION',
                    Str::limit($exception->getMessage(), 1000)
                );
            }
        }
    }

    private function hasValidVapidConfiguration(): bool
    {
        return (string) config('webpush.vapid.subject') !== ''
            && (string) config('webpush.vapid.public_key') !== ''
            && (string) config('webpush.vapid.private_key') !== '';
    }

    private function hasValidFcmConfiguration(): bool
    {
        return trim($this->resolveFcmConfigValue('fcm_project_id', 'services.fcm.project_id')) !== ''
            && trim($this->resolveFcmConfigValue('fcm_service_account_email', 'services.fcm.service_account_email')) !== ''
            && trim($this->resolveFcmConfigValue('fcm_service_account_private_key', 'services.fcm.service_account_private_key')) !== '';
    }

    private function getFcmAccessToken(): ?string
    {
        return Cache::remember($this->fcmAccessTokenCacheKey(), now()->addMinutes(50), function (): ?string {
            $clientEmail = trim($this->resolveFcmConfigValue('fcm_service_account_email', 'services.fcm.service_account_email'));
            $privateKey = str_replace("\\n", "\n", $this->resolveFcmConfigValue('fcm_service_account_private_key', 'services.fcm.service_account_private_key'));

            if ($clientEmail === '' || trim($privateKey) === '') {
                return null;
            }

            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ]));

            $issuedAt = time();
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ]));

            if (! is_string($header) || ! is_string($claims)) {
                return null;
            }

            $unsignedJwt = $header.'.'.$claims;
            $privateKeyResource = openssl_pkey_get_private($privateKey);

            if ($privateKeyResource === false) {
                return null;
            }

            $signature = '';
            $signed = openssl_sign($unsignedJwt, $signature, $privateKeyResource, 'sha256WithRSAEncryption');

            if (! $signed) {
                return null;
            }

            $assertion = $unsignedJwt.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $token = data_get($response->json(), 'access_token');

            return is_string($token) && $token !== '' ? $token : null;
        });
    }

    private function buildFcmMessage(string $token, array $payload): array
    {
        $title = (string) ($payload['title'] ?? 'Unsell');
        $body = (string) ($payload['body'] ?? 'You have a new update.');
        $icon = (string) ($payload['icon'] ?? asset('icons/icon.svg'));
        $badge = (string) ($payload['badge'] ?? asset('icons/icon.svg'));
        $sound = (string) ($payload['sound'] ?? setting('notification_sound_url', ''));
        $url = (string) data_get($payload, 'data.url', $payload['url'] ?? url('/'));
        $tag = data_get($payload, 'tag');

        $webpushNotification = [
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'badge' => $badge,
            'renotify' => (bool) ($payload['renotify'] ?? false),
            'requireInteraction' => (bool) ($payload['requireInteraction'] ?? false),
        ];

        if (is_string($tag) && $tag !== '') {
            $webpushNotification['tag'] = $tag;
        }

        if ($sound !== '') {
            $webpushNotification['sound'] = $sound;
        }

        return [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->normalizeFcmData(array_merge((array) ($payload['data'] ?? []), [
                    'title' => $title,
                    'body' => $body,
                    'icon' => $icon,
                    'badge' => $badge,
                    'sound' => $sound,
                    'url' => $url,
                    'tag' => is_string($tag) ? $tag : '',
                ])),
                'webpush' => [
                    'headers' => [
                        'Urgency' => 'high',
                    ],
                    'notification' => $webpushNotification,
                    'fcm_options' => [
                        'link' => $url,
                    ],
                ],
            ],
        ];
    }

    private function normalizeFcmData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (! is_string($key) || $key === '' || $value === null) {
                continue;
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_scalar($value)) {
                $normalized[$key] = (string) $value;
                continue;
            }

            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

            if (is_string($encoded)) {
                $normalized[$key] = $encoded;
            }
        }

        return $normalized;
    }

    private function resolveFcmConfigValue(string $settingKey, string $configKey): string
    {
        $fromSettings = setting($settingKey, '');

        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return $fromSettings;
        }

        return (string) config($configKey, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSnapshot(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return [
            'raw' => Str::limit($response->body(), 2000),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    private function logFcmDelivery(
        UserPushSubscription $subscription,
        array $payload,
        string $status,
        ?int $responseStatus = null,
        ?array $responseBody = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        try {
            PushDeliveryLog::query()->create([
                'user_id' => $subscription->user_id,
                'push_subscription_id' => $subscription->id,
                'provider' => 'fcm',
                'status' => $status,
                'target' => $subscription->resolved_token,
                'response_status' => $responseStatus,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'payload' => $payload,
                'response_body' => $responseBody,
                'delivered_at' => $status === 'success' ? now() : null,
            ]);
        } catch (Throwable) {
            // Delivery logging should never break notification delivery.
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function fcmAccessTokenCacheKey(): string
    {
        return 'fcm.push.access_token';
    }
}
