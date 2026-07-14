<?php

namespace App\Services\AI;

class TrustSafetyService
{
    public function __construct(private readonly AiGatewayService $gateway)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function assessChatMessage(string $body): array
    {
        $message = trim($body);

        if (! (bool) setting('ai_fraud_detection_enabled', true)) {
            return [
                'blocked' => false,
                'risk_score' => 0,
                'reasons' => [],
                'image_urls' => [],
            ];
        }

        $riskScore = 0;
        $reasons = [];

        $lower = strtolower($message);

        $scamPhrases = [
            'advance payment', 'pay first', 'send otp', 'share otp', 'upi pin',
            'gift card', 'crypto', 'bitcoin', 'scan qr', 'qr code',
            'telegram only', 'western union', 'bank transfer only',
            'click this link', 'instant refund fee',
        ];

        foreach ($scamPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                $riskScore += 25;
                $reasons[] = 'Scam phrase detected: "'.$phrase.'"';
            }
        }

        $prohibitedTerms = [
            'weapon', 'gun', 'pistol', 'explosive', 'narcotic',
            'counterfeit', 'fake id', 'forged',
        ];

        foreach ($prohibitedTerms as $term) {
            if (str_contains($lower, $term)) {
                $riskScore += 20;
                $reasons[] = 'Potential prohibited item term: "'.$term.'"';
            }
        }

        $urls = $this->extractUrls($message);
        if ($urls !== []) {
            $riskScore += 6;
            $reasons[] = 'Contains external links';
        }

        $imageUrls = $this->extractImageUrls($urls);

        if ((bool) setting('ai_block_suspicious_chat_images', true) && $imageUrls !== []) {
            $imageAssessment = $this->assessImageUrls($imageUrls);
            $riskScore += (int) ($imageAssessment['risk_score'] ?? 0);
            $reasons = array_merge($reasons, (array) ($imageAssessment['reasons'] ?? []));
        }

        if ($this->gateway->shouldUseGemini() && $message !== '') {
            $aiAssessment = $this->assessWithLlm($message, $imageUrls);
            $riskScore = (int) round(($riskScore * 0.6) + (((int) ($aiAssessment['risk_score'] ?? 0)) * 0.4));
            $reasons = array_values(array_unique(array_merge($reasons, (array) ($aiAssessment['reasons'] ?? []))));
        }

        $riskScore = max(0, min(100, $riskScore));
        $threshold = (int) setting('ai_confidence_threshold', 70);

        $blocked = (bool) setting('ai_block_scam_messages', true) && $riskScore >= $threshold;

        return [
            'blocked' => $blocked,
            'risk_score' => $riskScore,
            'reasons' => array_values(array_unique($reasons)),
            'image_urls' => $imageUrls,
        ];
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, mixed>
     */
    private function assessImageUrls(array $urls): array
    {
        $riskScore = 0;
        $reasons = [];

        foreach ($urls as $url) {
            $lowerUrl = strtolower($url);
            if (str_contains($lowerUrl, 'bit.ly') || str_contains($lowerUrl, 'tinyurl')) {
                $riskScore += 12;
                $reasons[] = 'Image link uses URL shortener';
            }
            if (str_contains($lowerUrl, 't.me') || str_contains($lowerUrl, 'telegram')) {
                $riskScore += 18;
                $reasons[] = 'Image link points to high-risk external channel';
            }
            if (preg_match('/\b(qr|payment|upi|bank|otp)\b/i', $lowerUrl) === 1) {
                $riskScore += 14;
                $reasons[] = 'Image URL metadata suggests payment/OTP bait';
            }
        }

        return [
            'risk_score' => $riskScore,
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<int, string>  $imageUrls
     * @return array<string, mixed>
     */
    private function assessWithLlm(string $message, array $imageUrls): array
    {
        $prompt = json_encode([
            'task' => 'Classify scam/fraud risk for marketplace chat message.',
            'message' => $message,
            'image_urls' => $imageUrls,
            'response_schema' => [
                'risk_score' => 'integer 0-100',
                'reasons' => ['string'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->gateway->completeText(
            'You are a trust-and-safety classifier. Return strict JSON only.',
            (string) $prompt,
            ['max_tokens' => 220, 'temperature' => 0.0]
        );

        $json = $this->gateway->extractJsonObject((string) ($response['content'] ?? ''));
        if (! is_array($json)) {
            return ['risk_score' => 0, 'reasons' => []];
        }

        $riskScore = (int) ($json['risk_score'] ?? 0);
        $reasons = array_values(array_filter(array_map('strval', (array) ($json['reasons'] ?? []))));

        return [
            'risk_score' => max(0, min(100, $riskScore)),
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractUrls(string $message): array
    {
        preg_match_all('/https?:\/\/[^\s]+/i', $message, $matches);

        return array_values(array_unique(array_map(
            static fn ($url): string => trim((string) $url),
            (array) ($matches[0] ?? [])
        )));
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function extractImageUrls(array $urls): array
    {
        $imageUrls = [];

        foreach ($urls as $url) {
            $path = strtolower((string) parse_url($url, PHP_URL_PATH));

            if (
                str_ends_with($path, '.jpg')
                || str_ends_with($path, '.jpeg')
                || str_ends_with($path, '.png')
                || str_ends_with($path, '.webp')
                || str_ends_with($path, '.gif')
            ) {
                $imageUrls[] = $url;
            }
        }

        return array_values(array_unique($imageUrls));
    }
}
