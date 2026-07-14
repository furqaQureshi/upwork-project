<?php

namespace App\Services\AI;

use App\Models\Listing;

class AiListingAssistantService
{
    public function __construct(private readonly AiGatewayService $gateway)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, mixed>  $images
     * @return array<string, mixed>
     */
    public function generateDraft(array $payload, array $images = []): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $categoryName = trim((string) ($payload['category_name'] ?? ''));
        $condition = trim((string) ($payload['condition'] ?? 'used'));
        $city = trim((string) ($payload['city'] ?? ''));
        $state = trim((string) ($payload['state'] ?? ''));

        $priceRecommendation = $this->recommendPrice($payload);

        $systemPrompt = 'You are a classified marketplace AI assistant. Return STRICT JSON only.';
        $userPrompt = json_encode([
            'task' => 'Generate listing draft from partial context and photos.',
            'output_schema' => [
                'title' => 'string max 140 chars',
                'description' => 'string 60-260 words',
                'attributes' => [
                    ['key' => 'string', 'value' => 'string'],
                ],
                'pros' => ['string'],
                'tradeoffs' => ['string'],
                'video_script' => 'string max 220 chars',
            ],
            'context' => [
                'title' => $title,
                'description' => $description,
                'category' => $categoryName,
                'condition' => $condition,
                'city' => $city,
                'state' => $state,
                'suggested_price' => $priceRecommendation['suggested_price'],
            ],
            'rules' => [
                'Keep output factual and buyer-focused.',
                'Avoid fake guarantees or unsupported claims.',
                'Use concise Indian marketplace style language.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = ['provider' => 'local', 'content' => '', 'raw' => null, 'error' => null];
        if ($this->gateway->shouldUseGemini()) {
            $response = $this->gateway->completeWithImages($systemPrompt, (string) $userPrompt, $images, [
                'max_tokens' => 1400,
                'temperature' => 0.35,
            ]);
        }

        $parsed = $this->gateway->extractJsonObject((string) ($response['content'] ?? ''));
        if (! is_array($parsed)) {
            $parsed = [];
        }

        $generatedTitle = $this->sanitizeTitle((string) ($parsed['title'] ?? ''));
        if ($generatedTitle === '') {
            $generatedTitle = $this->fallbackTitle($title, $categoryName, $city, $condition);
        }

        $generatedDescription = trim((string) ($parsed['description'] ?? ''));
        if ($generatedDescription === '') {
            $generatedDescription = $this->fallbackDescription($generatedTitle, $condition, $city, $state, $description);
        }

        $attributes = $this->normalizeAttributes($parsed['attributes'] ?? null, $condition, $city, $state, $categoryName);
        $duplicateCandidates = (bool) setting('ai_duplicate_detection_enabled', true)
            ? $this->findDuplicateCandidates($generatedTitle, $generatedDescription, (int) ($payload['category_id'] ?? 0))
            : [];

        $highestDuplicateScore = 0;
        if ($duplicateCandidates !== []) {
            $highestDuplicateScore = (int) max(array_column($duplicateCandidates, 'similarity_score'));
        }

        $duplicateRisk = 'low';
        if ($highestDuplicateScore >= 80) {
            $duplicateRisk = 'high';
        } elseif ($highestDuplicateScore >= 60) {
            $duplicateRisk = 'medium';
        }

        $timeSavingsMin = (int) setting('ai_time_savings_min', 35);
        $timeSavingsMax = (int) setting('ai_time_savings_max', 55);
        if ($timeSavingsMin > $timeSavingsMax) {
            [$timeSavingsMin, $timeSavingsMax] = [$timeSavingsMax, $timeSavingsMin];
        }

        $qualityImprovementMax = (int) setting('ai_quality_improvement_max', 37);

        $pros = array_values(array_filter(array_map('strval', (array) ($parsed['pros'] ?? []))));
        $tradeoffs = array_values(array_filter(array_map('strval', (array) ($parsed['tradeoffs'] ?? []))));
        $videoScript = trim((string) ($parsed['video_script'] ?? ''));
        if ($videoScript === '') {
            $videoScript = 'Quick walkaround: condition, highlights, and honest details for confident buyers.';
        }

        $imageOptimizationTips = [];
        if ((bool) setting('ai_image_optimization_enabled', true)) {
            $imageOptimizationTips = [
                'Use daylight shots and keep the primary photo clutter-free.',
                'Add at least one close-up of condition details and accessories.',
                'Keep horizon straight and avoid heavy filters to improve trust.',
            ];
        }

        $arCameraHint = null;
        if ((bool) setting('ai_ar_car_features_enabled', false) && preg_match('/car|bike|vehicle|auto/i', $categoryName) === 1) {
            $arCameraHint = 'Use AR camera mode to capture 360 exterior, dashboard, odometer, and tire condition walkthrough.';
        }

        return [
            'title' => $generatedTitle,
            'description' => $generatedDescription,
            'attributes' => $attributes,
            'pros' => $pros,
            'tradeoffs' => $tradeoffs,
            'video_script' => $videoScript,
            'image_optimization_tips' => $imageOptimizationTips,
            'ar_camera_hint' => $arCameraHint,
            'price_recommendation' => $priceRecommendation,
            'duplicate_risk' => $duplicateRisk,
            'duplicate_candidates' => $duplicateCandidates,
            'time_savings_percent' => [
                'min' => max(1, min(100, $timeSavingsMin)),
                'max' => max(1, min(100, $timeSavingsMax)),
            ],
            'quality_improvement_percent' => max(1, min(100, $qualityImprovementMax)),
            'provider' => (string) ($response['provider'] ?? 'local'),
            'provider_error' => $response['error'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function recommendPrice(array $payload): array
    {
        if (! (bool) setting('ai_price_recommendation_enabled', true)) {
            return [
                'currency' => 'INR',
                'sample_size' => 0,
                'suggested_price' => null,
                'min_price' => null,
                'max_price' => null,
                'method' => 'disabled',
            ];
        }

        $categoryId = (int) ($payload['category_id'] ?? 0);
        $city = trim((string) ($payload['city'] ?? ''));
        $condition = strtolower(trim((string) ($payload['condition'] ?? 'used')));

        $query = Listing::query()
            ->approved()
            ->where('price', '>', 0)
            ->when($categoryId > 0, function ($builder) use ($categoryId): void {
                $builder->where('category_id', $categoryId);
            })
            ->when($city !== '', function ($builder) use ($city): void {
                $builder->where('city', 'like', '%'.$city.'%');
            })
            ->latest('published_at')
            ->limit(140);

        $prices = $query
            ->pluck('price')
            ->map(static fn ($price): float => (float) $price)
            ->filter(static fn (float $price): bool => $price > 0)
            ->values()
            ->all();

        if ($prices === []) {
            $prices = Listing::query()
                ->approved()
                ->where('price', '>', 0)
                ->latest('published_at')
                ->limit(140)
                ->pluck('price')
                ->map(static fn ($price): float => (float) $price)
                ->filter(static fn (float $price): bool => $price > 0)
                ->values()
                ->all();
        }

        if ($prices === []) {
            return [
                'currency' => 'INR',
                'sample_size' => 0,
                'suggested_price' => null,
                'min_price' => null,
                'max_price' => null,
                'method' => 'insufficient_data',
            ];
        }

        sort($prices);
        $median = $this->median($prices);

        $conditionMultiplier = match ($condition) {
            'new' => 1.08,
            'refurbished' => 0.94,
            default => 1.00,
        };

        $suggested = max(1, (int) round($median * $conditionMultiplier));
        $minPrice = max(1, (int) round($suggested * 0.88));
        $maxPrice = max($minPrice + 1, (int) round($suggested * 1.12));

        return [
            'currency' => (string) setting('site_currency', 'INR'),
            'sample_size' => count($prices),
            'suggested_price' => $suggested,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'method' => 'market_median',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findDuplicateCandidates(string $title, string $description, int $categoryId): array
    {
        if ($title === '' && $description === '') {
            return [];
        }

        $query = Listing::query()
            ->approved()
            ->when($categoryId > 0, function ($builder) use ($categoryId): void {
                $builder->where('category_id', $categoryId);
            })
            ->latest('published_at')
            ->limit(80);

        $candidates = [];

        foreach ($query->get(['id', 'slug', 'title', 'description', 'price', 'city']) as $listing) {
            $titleScore = $this->stringSimilarity($title, (string) $listing->title);
            $descScore = $this->stringSimilarity($description, (string) $listing->description);
            $combined = (int) round(($titleScore * 0.72) + ($descScore * 0.28));

            if ($combined < 55) {
                continue;
            }

            $candidates[] = [
                'listing_id' => $listing->id,
                'slug' => $listing->slug,
                'title' => (string) $listing->title,
                'city' => (string) $listing->city,
                'price' => (float) $listing->price,
                'similarity_score' => $combined,
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['similarity_score'] <=> $a['similarity_score']);

        return array_slice($candidates, 0, 5);
    }

    /**
     * @return array<int, array{key:string,value:string}>
     */
    private function normalizeAttributes(mixed $incoming, string $condition, string $city, string $state, string $category): array
    {
        $attributes = [];

        if (is_array($incoming)) {
            foreach ($incoming as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $key = trim((string) ($item['key'] ?? ''));
                $value = trim((string) ($item['value'] ?? ''));
                if ($key === '' || $value === '') {
                    continue;
                }

                $attributes[] = [
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        if ($attributes === []) {
            if ($condition !== '') {
                $attributes[] = ['key' => 'Condition', 'value' => ucfirst($condition)];
            }
            if ($city !== '') {
                $attributes[] = ['key' => 'City', 'value' => $city];
            }
            if ($state !== '') {
                $attributes[] = ['key' => 'State', 'value' => $state];
            }
            if ($category !== '') {
                $attributes[] = ['key' => 'Category', 'value' => $category];
            }
        }

        return array_slice($attributes, 0, 8);
    }

    private function fallbackTitle(string $title, string $category, string $city, string $condition): string
    {
        if ($title !== '') {
            return $this->sanitizeTitle($title);
        }

        $parts = array_values(array_filter([
            $category !== '' ? $category : 'Marketplace Item',
            $condition !== '' ? ucfirst($condition) : null,
            $city !== '' ? 'in '.$city : null,
        ]));

        return $this->sanitizeTitle(implode(' ', $parts));
    }

    private function fallbackDescription(string $title, string $condition, string $city, string $state, string $existingDescription): string
    {
        if ($existingDescription !== '') {
            return $existingDescription;
        }

        $location = array_values(array_filter([$city, $state]));
        $locationText = $location !== [] ? implode(', ', $location) : 'your area';

        $lines = [
            $title !== '' ? $title : 'Great value item available now.',
            'Condition: '.($condition !== '' ? ucfirst($condition) : 'Well maintained').'.',
            'Location: '.$locationText.'.',
            'Serious buyers can message for full details, availability, and negotiation scope.',
        ];

        return implode(' ', $lines);
    }

    private function sanitizeTitle(string $title): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $title) ?? '');

        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, 140);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private function stringSimilarity(string $left, string $right): int
    {
        $a = strtolower(trim($left));
        $b = strtolower(trim($right));

        if ($a === '' || $b === '') {
            return 0;
        }

        similar_text($a, $b, $percentage);

        return (int) round(max(0.0, min(100.0, $percentage)));
    }
}
