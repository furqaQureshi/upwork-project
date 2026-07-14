<?php

namespace App\Services\AI;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class SeoRankService
{
    public function __construct(private readonly AiGatewayService $gateway)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function runAuditAndOptimize(bool $force = false): array
    {
        if (! (bool) setting('ai_enabled', false)) {
            return [
                'status' => 'skipped',
                'reason' => 'ai_suite_disabled',
                'message' => 'AI Suite is disabled.',
            ];
        }

        if (! (bool) setting('ai_seo_optimizer_enabled', true)) {
            return [
                'status' => 'skipped',
                'reason' => 'ai_seo_disabled',
                'message' => 'AI SEO Optimizer is disabled.',
            ];
        }

        $intervalMinutes = max(5, min(1440, (int) setting('ai_seo_audit_interval_minutes', 60)));

        if (! $force) {
            $lastRunAtRaw = trim((string) setting('ai_seo_last_run_at', ''));
            if ($lastRunAtRaw !== '') {
                try {
                    $lastRun = Carbon::parse($lastRunAtRaw);
                    if ($lastRun->diffInMinutes(now()) < $intervalMinutes) {
                        return [
                            'status' => 'skipped',
                            'reason' => 'interval_not_elapsed',
                            'message' => 'SEO audit interval has not elapsed yet.',
                            'last_run_at' => $lastRun->toIso8601String(),
                            'interval_minutes' => $intervalMinutes,
                        ];
                    }
                } catch (Throwable) {
                    // If parsing fails we continue with a fresh run.
                }
            }
        }

        $snapshot = $this->buildSnapshot();
        $currentMetaDescription = trim((string) setting('seo_meta_description', ''));
        $currentKeywords = $this->normalizeKeywords(
            explode(',', (string) setting('seo_meta_keywords', '')),
            max(5, min(40, (int) setting('ai_seo_max_keywords', 14)))
        );

        $fallback = $this->buildFallbackRecommendation($snapshot);
        $aiRecommendation = $this->requestAiRecommendation($snapshot, $currentMetaDescription, $currentKeywords);

        $metaDescription = $aiRecommendation['meta_description'] ?? $fallback['meta_description'];
        $keywords = $this->normalizeKeywords(
            $aiRecommendation['keywords'] ?? $fallback['keywords'],
            max(5, min(40, (int) setting('ai_seo_max_keywords', 14)))
        );
        $actionPlan = array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            (array) ($aiRecommendation['action_plan'] ?? $fallback['action_plan'])
        )));

        if ($actionPlan === []) {
            $actionPlan = (array) $fallback['action_plan'];
        }

        $score = $this->calculateSeoScore($metaDescription, $keywords, $snapshot);
        $provider = (string) ($aiRecommendation['provider'] ?? ($this->gateway->shouldUseGemini() ? 'gemini' : 'heuristic'));
        $providerError = $aiRecommendation['provider_error'] ?? null;

        $autoApply = (bool) setting('ai_seo_auto_apply_enabled', true);
        if ($autoApply) {
            $this->upsertSetting(
                key: 'seo_meta_description',
                value: $metaDescription,
                type: 'string',
                group: 'marketing',
                label: 'SEO meta description',
                description: 'Default description tag used across the website.'
            );

            $this->upsertSetting(
                key: 'seo_meta_keywords',
                value: implode(', ', $keywords),
                type: 'string',
                group: 'marketing',
                label: 'SEO keywords',
                description: 'Comma-separated default keywords for search engines.'
            );
        }

        $this->persistAuditResult(
            score: $score,
            provider: $provider,
            summary: (string) ($fallback['summary'] ?? 'AI SEO audit completed.'),
            keywords: $keywords,
            actionPlan: $actionPlan
        );

        $this->syncSitemapFile();

        AppSetting::clearCache();

        return [
            'status' => 'completed',
            'provider' => $provider,
            'provider_error' => $providerError,
            'applied' => $autoApply,
            'score' => $score,
            'meta_description' => $metaDescription,
            'keywords' => $keywords,
            'action_plan' => $actionPlan,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(): array
    {
        $lookbackDays = max(7, min(180, (int) setting('ai_seo_lookback_days', 30)));

        $listings = Listing::query()
            ->approved()
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays($lookbackDays))
            ->latest('published_at')
            ->limit(500)
            ->get(['id', 'category_id', 'title', 'description', 'city', 'views']);

        if ($listings->isEmpty()) {
            $listings = Listing::query()
                ->approved()
                ->latest('published_at')
                ->limit(500)
                ->get(['id', 'category_id', 'title', 'description', 'city', 'views']);
        }

        $categoryCounts = $listings
            ->pluck('category_id')
            ->filter(static fn ($id): bool => (int) $id > 0)
            ->countBy()
            ->sortDesc();

        $categoryNames = Category::query()
            ->whereIn('id', $categoryCounts->keys()->map(static fn ($id): int => (int) $id)->all())
            ->pluck('name', 'id')
            ->all();

        $topCategories = [];
        foreach ($categoryCounts->take(6) as $categoryId => $count) {
            $topCategories[] = [
                'category_id' => (int) $categoryId,
                'name' => (string) ($categoryNames[(int) $categoryId] ?? 'Unknown'),
                'count' => (int) $count,
            ];
        }

        $cityCounts = $listings
            ->pluck('city')
            ->map(static fn ($city): string => trim((string) $city))
            ->filter(static fn ($city): bool => $city !== '')
            ->countBy()
            ->sortDesc();

        $topCities = [];
        foreach ($cityCounts->take(6) as $city => $count) {
            $topCities[] = [
                'city' => (string) $city,
                'count' => (int) $count,
            ];
        }

        $topListings = $listings
            ->sortByDesc(static fn (Listing $listing): int => (int) $listing->views)
            ->take(12)
            ->values()
            ->map(static fn (Listing $listing): array => [
                'title' => (string) $listing->title,
                'description' => mb_substr(trim((string) $listing->description), 0, 220),
                'city' => (string) $listing->city,
                'views' => (int) $listing->views,
            ])
            ->all();

        $maxKeywords = max(5, min(40, (int) setting('ai_seo_max_keywords', 14)));
        $keywords = $this->extractTrendingKeywords($listings, $maxKeywords);

        return [
            'lookback_days' => $lookbackDays,
            'listing_count' => $listings->count(),
            'top_categories' => $topCategories,
            'top_cities' => $topCities,
            'top_listings' => $topListings,
            'keywords' => $keywords,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Listing>  $listings
     * @return array<int, string>
     */
    private function extractTrendingKeywords($listings, int $limit): array
    {
        $stopWords = [
            'the', 'and', 'for', 'with', 'from', 'this', 'that', 'your', 'you', 'are', 'was', 'were', 'have',
            'has', 'had', 'our', 'new', 'used', 'sale', 'best', 'very', 'good', 'item', 'items', 'near', 'all',
            'any', 'can', 'will', 'just', 'only', 'now', 'today', 'its', 'it', 'in', 'on', 'at', 'of', 'to',
        ];

        $wordFrequency = [];

        foreach ($listings as $listing) {
            $text = strtolower(trim((string) $listing->title.' '.(string) $listing->description.' '.(string) $listing->city));
            if ($text === '') {
                continue;
            }

            preg_match_all('/[a-z0-9]+/i', $text, $matches);
            foreach ((array) ($matches[0] ?? []) as $token) {
                $word = trim((string) $token);

                if ($word === '' || strlen($word) < 3 || in_array($word, $stopWords, true) || is_numeric($word)) {
                    continue;
                }

                $wordFrequency[$word] = (int) ($wordFrequency[$word] ?? 0) + 1;
            }
        }

        arsort($wordFrequency);

        return $this->normalizeKeywords(array_keys(array_slice($wordFrequency, 0, $limit * 3, true)), $limit);
    }

    /**
     * @param  array<int, mixed>  $keywords
     * @return array<int, string>
     */
    private function normalizeKeywords(array $keywords, int $limit): array
    {
        $normalized = [];

        foreach ($keywords as $keyword) {
            $value = strtolower(trim((string) $keyword));
            $value = preg_replace('/\s+/', ' ', $value) ?? $value;
            $value = trim($value, " \t\n\r\0\x0B,;");

            if ($value === '' || strlen($value) < 2) {
                continue;
            }

            if (! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }

            if (count($normalized) >= $limit) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function buildFallbackRecommendation(array $snapshot): array
    {
        $siteName = trim((string) setting('site_name', config('app.name', 'Unsell')));

        $categoryNames = array_values(array_filter(array_map(
            static fn (array $item): string => trim((string) ($item['name'] ?? '')),
            (array) ($snapshot['top_categories'] ?? [])
        )));

        $cityNames = array_values(array_filter(array_map(
            static fn (array $item): string => trim((string) ($item['city'] ?? '')),
            (array) ($snapshot['top_cities'] ?? [])
        )));

        $primaryCategories = implode(', ', array_slice($categoryNames, 0, 2));
        $primaryCities = implode(', ', array_slice($cityNames, 0, 2));

        $metaDescription = 'Buy and sell locally with verified listings, smart pricing insights, and secure chat on '.$siteName.'.';

        if ($primaryCategories !== '' && $primaryCities !== '') {
            $metaDescription = 'Buy and sell '.$primaryCategories.' in '.$primaryCities.' with verified listings, smart pricing insights, and secure chat on '.$siteName.'.';
        } elseif ($primaryCategories !== '') {
            $metaDescription = 'Buy and sell '.$primaryCategories.' with verified listings, smart pricing insights, and secure chat on '.$siteName.'.';
        } elseif ($primaryCities !== '') {
            $metaDescription = 'Buy and sell locally in '.$primaryCities.' with verified listings, smart pricing insights, and secure chat on '.$siteName.'.';
        }

        $metaDescription = $this->trimToLength($metaDescription, 158);

        $keywords = $this->normalizeKeywords((array) ($snapshot['keywords'] ?? []), max(5, min(40, (int) setting('ai_seo_max_keywords', 14))));

        if ($keywords === []) {
            $keywords = ['marketplace', 'buy online', 'sell online', 'local deals', 'classified ads'];
        }

        $actionPlan = [
            'Refresh stale listings weekly with updated photos and buyer-focused intros.',
            'Target category + city combinations with strongest view growth.',
            'Keep title keywords natural and include key buyer intent phrases.',
            'Add internal links from homepage widgets to top-converting categories.',
        ];

        $summary = 'Automated AI SEO audit completed using marketplace listing trends and engagement signals.';

        return [
            'meta_description' => $metaDescription,
            'keywords' => $keywords,
            'action_plan' => $actionPlan,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, string>  $currentKeywords
     * @return array<string, mixed>|null
     */
    private function requestAiRecommendation(array $snapshot, string $currentMetaDescription, array $currentKeywords): ?array
    {
        if (! $this->gateway->shouldUseGemini()) {
            return null;
        }

        $prompt = json_encode([
            'task' => 'Create advanced SEO metadata recommendations for a marketplace homepage.',
            'response_schema' => [
                'meta_description' => 'string, max 160 chars',
                'keywords' => ['string'],
                'action_plan' => ['string'],
            ],
            'constraints' => [
                'Do not use hype or fake guarantees.',
                'Use clear buyer and seller intent language.',
                'Keep action_plan practical and implementation-ready.',
            ],
            'current_meta_description' => $currentMetaDescription,
            'current_keywords' => $currentKeywords,
            'market_snapshot' => $snapshot,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->gateway->completeText(
            'You are an SEO strategist for a classifieds marketplace. Return strict JSON only.',
            (string) $prompt,
            [
                'max_tokens' => 700,
                'temperature' => 0.25,
            ]
        );

        $json = $this->gateway->extractJsonObject((string) ($response['content'] ?? ''));
        if (! is_array($json)) {
            return null;
        }

        $description = trim((string) ($json['meta_description'] ?? ''));
        if ($description === '') {
            return null;
        }

        $keywordsRaw = $json['keywords'] ?? [];
        if (is_string($keywordsRaw)) {
            $keywordsRaw = explode(',', $keywordsRaw);
        }

        $keywords = $this->normalizeKeywords((array) $keywordsRaw, max(5, min(40, (int) setting('ai_seo_max_keywords', 14))));

        $actionPlan = array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            (array) ($json['action_plan'] ?? [])
        )));

        return [
            'meta_description' => $this->trimToLength($description, 158),
            'keywords' => $keywords,
            'action_plan' => $actionPlan,
            'provider' => (string) ($response['provider'] ?? 'gemini'),
            'provider_error' => $response['error'] ?? null,
        ];
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array<string, mixed>  $snapshot
     */
    private function calculateSeoScore(string $metaDescription, array $keywords, array $snapshot): int
    {
        $score = 0;

        $descriptionLength = strlen(trim($metaDescription));
        if ($descriptionLength >= 130 && $descriptionLength <= 160) {
            $score += 30;
        } elseif ($descriptionLength >= 100 && $descriptionLength <= 180) {
            $score += 20;
        } elseif ($descriptionLength > 0) {
            $score += 10;
        }

        $keywordCount = count($keywords);
        if ($keywordCount >= 8 && $keywordCount <= 18) {
            $score += 25;
        } elseif ($keywordCount >= 5) {
            $score += 16;
        } elseif ($keywordCount > 0) {
            $score += 8;
        }

        $targetKeywords = array_slice((array) ($snapshot['keywords'] ?? []), 0, 8);
        $coverageHits = 0;
        $keywordCorpus = strtolower(implode(' ', $keywords).' '.$metaDescription);

        foreach ($targetKeywords as $targetKeyword) {
            if (str_contains($keywordCorpus, strtolower((string) $targetKeyword))) {
                $coverageHits++;
            }
        }

        $score += min(30, $coverageHits * 4);

        $listingCount = (int) ($snapshot['listing_count'] ?? 0);
        if ($listingCount >= 50) {
            $score += 15;
        } elseif ($listingCount >= 15) {
            $score += 10;
        } elseif ($listingCount > 0) {
            $score += 5;
        }

        return max(1, min(100, $score));
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array<int, string>  $actionPlan
     */
    private function persistAuditResult(int $score, string $provider, string $summary, array $keywords, array $actionPlan): void
    {
        $this->upsertSetting(
            key: 'ai_seo_last_run_at',
            value: now()->toIso8601String(),
            type: 'string',
            group: 'ai',
            label: 'AI SEO last run at',
            description: 'Timestamp when AI SEO optimizer last completed an audit run.'
        );

        $this->upsertSetting(
            key: 'ai_seo_last_score',
            value: $score,
            type: 'integer',
            group: 'ai',
            label: 'AI SEO last score',
            description: 'Last computed SEO health score generated by AI optimizer.'
        );

        $this->upsertSetting(
            key: 'ai_seo_last_provider',
            value: $provider,
            type: 'string',
            group: 'ai',
            label: 'AI SEO last provider',
            description: 'Provider used in the latest AI SEO optimization run.'
        );

        $this->upsertSetting(
            key: 'ai_seo_last_summary',
            value: $this->trimToLength($summary, 1900),
            type: 'string',
            group: 'ai',
            label: 'AI SEO last summary',
            description: 'Summary from the most recent AI SEO optimizer run.'
        );

        $this->upsertSetting(
            key: 'ai_seo_last_keywords',
            value: $keywords,
            type: 'json',
            group: 'ai',
            label: 'AI SEO last keywords',
            description: 'Keyword list generated by the latest AI SEO optimizer run.'
        );

        $this->upsertSetting(
            key: 'ai_seo_last_actions',
            value: $actionPlan,
            type: 'json',
            group: 'ai',
            label: 'AI SEO last actions',
            description: 'Action items generated by the latest AI SEO optimizer run.'
        );
    }

    private function syncSitemapFile(): void
    {
        if (! (bool) setting('ai_seo_generate_sitemap', true)) {
            return;
        }

        try {
            File::put(public_path('sitemap.xml'), $this->buildSitemapXml());
        } catch (Throwable $throwable) {
            Log::warning('AI SEO sitemap generation failed.', [
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function buildSitemapXml(): string
    {
        $rows = [
            [
                'loc' => route('home'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'hourly',
                'priority' => '1.0',
            ],
            [
                'loc' => route('categories.index'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ],
            [
                'loc' => route('menu.index'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.7',
            ],
        ];

        $listingRows = Listing::query()
            ->approved()
            ->whereNotNull('published_at')
            ->latest('updated_at')
            ->limit(10000)
            ->get(['slug', 'updated_at', 'published_at'])
            ->map(static function (Listing $listing): array {
                $lastModified = $listing->updated_at ?? $listing->published_at ?? now();

                return [
                    'loc' => route('listings.show', $listing->slug),
                    'lastmod' => $lastModified->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '0.7',
                ];
            })
            ->all();

        $rows = array_merge($rows, $listingRows);

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($rows as $row) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.htmlspecialchars((string) $row['loc'], ENT_QUOTES | ENT_XML1).'</loc>';
            $xml[] = '    <lastmod>'.htmlspecialchars((string) $row['lastmod'], ENT_QUOTES | ENT_XML1).'</lastmod>';
            $xml[] = '    <changefreq>'.htmlspecialchars((string) $row['changefreq'], ENT_QUOTES | ENT_XML1).'</changefreq>';
            $xml[] = '    <priority>'.htmlspecialchars((string) $row['priority'], ENT_QUOTES | ENT_XML1).'</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml)."\n";
    }

    private function trimToLength(string $value, int $maxLength): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return rtrim(substr($normalized, 0, $maxLength - 1)).'.';
    }

    private function upsertSetting(string $key, mixed $value, string $type, string $group, string $label, string $description): void
    {
        $encodedValue = match ($type) {
            'boolean' => (bool) $value ? '1' : '0',
            'integer' => (string) ((int) $value),
            'json' => json_encode(array_values((array) $value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };

        $existing = AppSetting::query()->find($key);

        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $encodedValue,
                'type' => $type,
                'group' => $existing?->group ?? $group,
                'label' => $existing?->label ?? $label,
                'description' => $existing?->description ?? $description,
            ]
        );
    }
}
