<?php

namespace App\Services\AI;

class LanguageGatewayService
{
    public function __construct(private readonly AiGatewayService $aiGatewayService)
    {
    }

    /**
     * @param  array<int, array<string, string>>  $history
     * @return array<string, mixed>
     */
    public function prepareInbound(
        string $query,
        array $history = [],
        string $locationLabel = '',
        ?string $uiLanguage = null
    ): array {
        $trimmedQuery = trim($query);
        $sourceLanguage = $this->detectLanguage($trimmedQuery);
        $preferredLanguage = $this->normalizeLanguageCode((string) ($uiLanguage ?? 'auto'));
        $responseLanguage = $preferredLanguage === 'auto' ? $sourceLanguage : $preferredLanguage;

        $translatedInbound = false;
        $provider = 'local';

        $safeHistory = array_values(array_map(static function (array $turn): array {
            return [
                'role' => (string) ($turn['role'] ?? 'user'),
                'content' => trim((string) ($turn['content'] ?? '')),
            ];
        }, $history));

        $translatedQuery = $trimmedQuery;
        $translatedLocation = trim($locationLabel);
        $translatedHistory = $safeHistory;

        if ($this->shouldTranslate($sourceLanguage, 'en') && $this->canUseProvider()) {
            $provider = 'gemini';

            $payload = [];
            $payload[] = ['key' => 'query', 'text' => $trimmedQuery];

            if ($translatedLocation !== '') {
                $payload[] = ['key' => 'location', 'text' => $translatedLocation];
            }

            foreach ($safeHistory as $idx => $turn) {
                $content = trim((string) ($turn['content'] ?? ''));
                if ($content === '') {
                    continue;
                }

                $payload[] = ['key' => 'history_'.$idx, 'text' => $content];
            }

            $translatedMap = $this->translateBatch($payload, $sourceLanguage, 'en');
            if ($translatedMap !== []) {
                $translatedInbound = true;
                $translatedQuery = $translatedMap['query'] ?? $trimmedQuery;
                $translatedLocation = $translatedMap['location'] ?? $translatedLocation;

                foreach ($translatedHistory as $idx => $turn) {
                    $key = 'history_'.$idx;
                    if (isset($translatedMap[$key])) {
                        $translatedHistory[$idx]['content'] = (string) $translatedMap[$key];
                    }
                }
            }
        }

        return [
            'query' => $translatedQuery,
            'history' => $translatedHistory,
            'location_label' => $translatedLocation,
            'detected_language' => $sourceLanguage,
            'response_language' => $responseLanguage,
            'translated_inbound' => $translatedInbound,
            'translation_provider' => $provider,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function localizeOutbound(array $response, string $responseLanguage): array
    {
        $targetLanguage = $this->normalizeLanguageCode($responseLanguage);
        if (! $this->shouldTranslate('en', $targetLanguage) || ! $this->canUseProvider()) {
            $response['detected_language'] = $response['detected_language'] ?? 'en';
            $response['response_language'] = $targetLanguage;
            $response['translated_outbound'] = false;

            return $response;
        }

        $payload = [];
        $summary = trim((string) ($response['summary'] ?? ''));
        if ($summary !== '') {
            $payload[] = ['key' => 'summary', 'text' => $summary];
        }

        $clarifying = trim((string) ($response['clarifying_question'] ?? ''));
        if ($clarifying !== '') {
            $payload[] = ['key' => 'clarifying_question', 'text' => $clarifying];
        }

        $translatedMap = $this->translateBatch($payload, 'en', $targetLanguage);
        if ($translatedMap !== []) {
            if (isset($translatedMap['summary'])) {
                $response['summary'] = $translatedMap['summary'];
            }

            if (isset($translatedMap['clarifying_question'])) {
                $response['clarifying_question'] = $translatedMap['clarifying_question'];
            }
        }

        $response['response_language'] = $targetLanguage;
        $response['translated_outbound'] = $translatedMap !== [];

        return $response;
    }

    public function canUseProvider(): bool
    {
        return false;
    }

    public function detectLanguage(string $text): string
    {
        $value = trim($text);
        if ($value === '') {
            return 'en';
        }

        if (preg_match('/\p{Devanagari}/u', $value) === 1) {
            return 'hi';
        }
        if (preg_match('/\p{Bengali}/u', $value) === 1) {
            return 'bn';
        }
        if (preg_match('/\p{Arabic}/u', $value) === 1) {
            return 'ar';
        }
        if (preg_match('/\p{Cyrillic}/u', $value) === 1) {
            return 'ru';
        }
        if (preg_match('/\p{Han}/u', $value) === 1) {
            return 'zh';
        }
        if (preg_match('/[\x{3040}-\x{30FF}]/u', $value) === 1) {
            return 'ja';
        }
        if (preg_match('/\p{Hangul}/u', $value) === 1) {
            return 'ko';
        }
        if (preg_match('/\p{Thai}/u', $value) === 1) {
            return 'th';
        }
        if (preg_match('/\p{Tamil}/u', $value) === 1) {
            return 'ta';
        }
        if (preg_match('/\p{Telugu}/u', $value) === 1) {
            return 'te';
        }
        if (preg_match('/\p{Kannada}/u', $value) === 1) {
            return 'kn';
        }
        if (preg_match('/\p{Malayalam}/u', $value) === 1) {
            return 'ml';
        }
        if (preg_match('/\p{Gujarati}/u', $value) === 1) {
            return 'gu';
        }
        if (preg_match('/\p{Gurmukhi}/u', $value) === 1) {
            return 'pa';
        }

        // Hinglish detection in Latin script.
        if (preg_match('/\b(kya|kaise|mujhe|chahiye|batao|dikhao|dhoond|dhund|sasta|mahenga|bechna|kharidna|kitna|mein|pas|acha|accha)\b/i', $value) === 1) {
            return 'hi';
        }

        // Lightweight Latin language hints.
        if (preg_match('/\b(hola|gracias|comprar|vender|precio|busco)\b/i', $value) === 1) {
            return 'es';
        }
        if (preg_match('/\b(bonjour|merci|acheter|vendre|prix|cherche)\b/i', $value) === 1) {
            return 'fr';
        }
        if (preg_match('/\b(hallo|danke|kaufen|verkaufen|preis|suche)\b/i', $value) === 1) {
            return 'de';
        }
        if (preg_match('/\b(ola|obrigado|comprar|vender|preco|procuro)\b/i', $value) === 1) {
            return 'pt';
        }
        if (preg_match('/\b(merhaba|tesekkur|sat\w+|al\w+|fiyat|ariyorum)\b/i', $value) === 1) {
            return 'tr';
        }

        return 'en';
    }

    private function shouldTranslate(string $source, string $target): bool
    {
        $sourceCode = $this->normalizeLanguageCode($source);
        $targetCode = $this->normalizeLanguageCode($target);

        return $sourceCode !== ''
            && $targetCode !== ''
            && $sourceCode !== 'auto'
            && $targetCode !== 'auto'
            && $sourceCode !== $targetCode;
    }

    private function normalizeLanguageCode(string $code): string
    {
        $value = strtolower(trim($code));
        if ($value === '') {
            return 'auto';
        }

        $map = [
            'en-us' => 'en',
            'en-gb' => 'en',
            'english' => 'en',
            'hindi' => 'hi',
            'hinglish' => 'hi',
            'bn-in' => 'bn',
            'ar-sa' => 'ar',
            'pt-br' => 'pt',
            'zh-cn' => 'zh',
            'zh-tw' => 'zh',
            'ja-jp' => 'ja',
            'ko-kr' => 'ko',
            'auto-detect' => 'auto',
            'default' => 'auto',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        // Convert BCP-47 style code like "es-ES" -> "es".
        if (str_contains($value, '-')) {
            $parts = explode('-', $value);

            return $parts[0] !== '' ? $parts[0] : 'auto';
        }

        return $value;
    }

    /**
     * @param  array<int, array{key:string,text:string}>  $items
     * @return array<string, string>
     */
    private function translateBatch(array $items, string $sourceLanguage, string $targetLanguage): array
    {
        $sanitized = array_values(array_filter(array_map(static function (array $item): ?array {
            $key = trim((string) ($item['key'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));
            if ($key === '' || $text === '') {
                return null;
            }

            return ['key' => $key, 'text' => $text];
        }, $items)));

        if ($sanitized === []) {
            return [];
        }

        $promptPayload = [
            'task' => 'Translate each text exactly to target language while preserving meaning, numbers, and currency. Return JSON object only.',
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'items' => $sanitized,
            'response_schema' => [
                'translations' => [
                    ['key' => 'string', 'text' => 'string'],
                ],
            ],
        ];

        $completion = $this->aiGatewayService->completeText(
            'You are a high-quality translation gateway for marketplace conversations. Return strict JSON only.',
            (string) json_encode($promptPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['max_tokens' => 1500, 'temperature' => 0.1]
        );

        $json = $this->aiGatewayService->extractJsonObject((string) ($completion['content'] ?? ''));
        if (! is_array($json)) {
            return [];
        }

        $rows = $json['translations'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            $text = trim((string) ($row['text'] ?? ''));
            if ($key === '' || $text === '') {
                continue;
            }

            $map[$key] = $text;
        }

        return $map;
    }
}
