<?php

namespace App\Services\AI;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiGatewayService
{
    public function isSuiteEnabled(): bool
    {
        return (bool) setting('ai_enabled', false);
    }

    public function provider(): string
    {
        $provider = strtolower(trim((string) setting('ai_provider', 'gemini')));

        // Migrate old provider value transparently.
        if ($provider === 'openai') {
            $provider = 'gemini';
        }

        if (! in_array($provider, ['mock', 'gemini'], true)) {
            $provider = 'gemini';
        }

        if (
            $provider === 'mock'
            && $this->geminiApiKey() !== ''
            && (bool) setting('ai_force_real_provider', true)
        ) {
            return 'gemini';
        }

        return $provider;
    }

    public function shouldUseGemini(): bool
    {
        $apiKey = $this->geminiApiKey();

        return $this->isSuiteEnabled() && $this->provider() === 'gemini' && $apiKey !== '';
    }

    public function shouldUseOpenAi(): bool
    {
        // Backward compatibility for existing callers.
        return $this->shouldUseGemini();
    }

    public function geminiApiKey(): string
    {
        $keyFromSettings = trim((string) setting('ai_gemini_api_key', ''));
        if ($keyFromSettings !== '') {
            return $keyFromSettings;
        }

        // Backward compatibility with old key storage.
        $legacySetting = trim((string) setting('ai_openai_api_key', ''));
        if ($legacySetting !== '') {
            return $legacySetting;
        }

        $keyFromConfig = trim((string) config('services.gemini.api_key', ''));
        if ($keyFromConfig !== '') {
            return $keyFromConfig;
        }

        $legacyConfig = trim((string) config('services.openai.api_key', ''));
        if ($legacyConfig !== '') {
            return $legacyConfig;
        }

        $keyFromEnv = trim((string) env('GEMINI_API_KEY', ''));
        if ($keyFromEnv !== '') {
            return $keyFromEnv;
        }

        return trim((string) env('OPENAI_API_KEY', ''));
    }

    /**
     * @return array{provider:string,content:string,raw:mixed,error:?string}
     */
    public function completeText(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        if (! $this->shouldUseGemini()) {
            return [
                'provider' => 'mock',
                'content' => '',
                'raw' => null,
                'error' => null,
            ];
        }

        $model = trim((string) ($options['model'] ?? setting('ai_gemini_model', setting('ai_openai_model', 'gemini-2.5-pro'))));

        try {
            $payload = $this->requestGeminiGenerateContent(
                systemPrompt: $systemPrompt,
                userParts: [
                    ['text' => $userPrompt],
                ],
                model: $model,
                maxTokens: (int) ($options['max_tokens'] ?? 1200),
                temperature: (float) ($options['temperature'] ?? 0.35)
            );

            return [
                'provider' => 'gemini',
                'content' => $this->extractContent($payload),
                'raw' => $payload,
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            Log::warning('AI text completion failed.', [
                'message' => $throwable->getMessage(),
            ]);

            return [
                'provider' => 'gemini',
                'content' => '',
                'raw' => null,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, UploadedFile|string>  $images
     * @return array{provider:string,content:string,raw:mixed,error:?string}
     */
    public function completeWithImages(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): array
    {
        if (! $this->shouldUseGemini()) {
            return [
                'provider' => 'mock',
                'content' => '',
                'raw' => null,
                'error' => null,
            ];
        }

        $model = trim((string) ($options['model'] ?? setting('ai_gemini_vision_model', setting('ai_gemini_model', setting('ai_openai_vision_model', 'gemini-2.5-pro')))));

        $content = [
            [
                'text' => $userPrompt,
            ],
        ];

        foreach (array_slice($images, 0, 4) as $image) {
            $inlineData = $this->encodeImageForGemini($image);
            if ($inlineData === null) {
                continue;
            }

            $content[] = [
                'inline_data' => $inlineData,
            ];
        }

        if (count($content) === 1) {
            return $this->completeText($systemPrompt, $userPrompt, $options);
        }

        try {
            $payload = $this->requestGeminiGenerateContent(
                systemPrompt: $systemPrompt,
                userParts: $content,
                model: $model,
                maxTokens: (int) ($options['max_tokens'] ?? 1200),
                temperature: (float) ($options['temperature'] ?? 0.3)
            );

            return [
                'provider' => 'gemini',
                'content' => $this->extractContent($payload),
                'raw' => $payload,
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            Log::warning('AI vision completion failed.', [
                'message' => $throwable->getMessage(),
            ]);

            return [
                'provider' => 'gemini',
                'content' => '',
                'raw' => null,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    public function extractJsonObject(string $content): ?array
    {
        $normalized = trim($content);

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '```')) {
            $normalized = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/```$/', '', trim($normalized)) ?? $normalized;
        }

        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($normalized, '{');
        $end = strrpos($normalized, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $slice = substr($normalized, $start, ($end - $start) + 1);
        $decoded = json_decode($slice, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $userParts
     */
    private function requestGeminiGenerateContent(string $systemPrompt, array $userParts, string $model, int $maxTokens, float $temperature): array
    {
        $apiKey = $this->geminiApiKey();
        $modelName = $model !== '' ? $model : 'gemini-2.5-pro';

        $response = Http::acceptJson()
            ->timeout(60)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$modelName.':generateContent?key='.$apiKey, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $userParts,
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => max(200, $maxTokens),
                    'temperature' => max(0.0, min(1.5, $temperature)),
                ],
            ]);

        $response->throw();

        return (array) $response->json();
    }

    private function extractContent(array $payload): string
    {
        $parts = data_get($payload, 'candidates.0.content.parts', []);

        if (! is_array($parts)) {
            return '';
        }

        $textParts = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $textParts[] = trim($part['text']);
            }
        }

        return trim(implode("\n", array_filter($textParts)));
    }

    private function encodeImageForGemini(UploadedFile|string $image): ?array
    {
        $binary = null;
        $mime = 'image/jpeg';

        if ($image instanceof UploadedFile) {
            $path = $image->getRealPath();
            if (! $path || ! is_file($path)) {
                return null;
            }

            $binary = @file_get_contents($path);
            $mime = $image->getMimeType() ?: $mime;
        } elseif (is_string($image)) {
            if (! is_file($image)) {
                return null;
            }

            $binary = @file_get_contents($image);
            $detectedMime = @mime_content_type($image);
            if (is_string($detectedMime) && str_starts_with($detectedMime, 'image/')) {
                $mime = $detectedMime;
            }
        }

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        return [
            'mime_type' => $mime,
            'data' => base64_encode($binary),
        ];
    }
}
