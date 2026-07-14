<?php

namespace App\Services\AI;

use App\Models\Category;
use App\Models\Listing;

class CvMatchingService
{
    public function __construct(private readonly AiGatewayService $gateway)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $cvText, int $limit = 8): array
    {
        $normalizedText = trim($cvText);
        $keywords = $this->extractKeywords($normalizedText);

        $jobCategoryIds = Category::query()
            ->where('name', 'like', '%job%')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $query = Listing::query()
            ->approved()
            ->when($jobCategoryIds !== [], function ($builder) use ($jobCategoryIds): void {
                $builder->whereIn('category_id', $jobCategoryIds);
            })
            ->latest('published_at')
            ->limit(120);

        $scored = [];

        foreach ($query->get(['id', 'slug', 'title', 'description', 'city', 'state', 'price']) as $listing) {
            $listingTokens = $this->extractKeywords($listing->title.' '.$listing->description);
            $score = $this->jaccardScore($keywords, $listingTokens);

            if ($score <= 0) {
                continue;
            }

            $matchedKeywords = array_values(array_intersect($keywords, $listingTokens));
            $missingKeywords = array_values(array_diff($keywords, $listingTokens));

            $scored[] = [
                'listing_id' => $listing->id,
                'slug' => $listing->slug,
                'title' => $listing->title,
                'city' => $listing->city,
                'state' => $listing->state,
                'salary_or_budget' => (float) $listing->price,
                'match_score' => (int) round($score * 100),
                'matched_keywords' => array_slice($matchedKeywords, 0, 8),
                'missing_keywords' => array_slice($missingKeywords, 0, 8),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['match_score'] <=> $a['match_score']);
        $matches = array_slice($scored, 0, max(1, $limit));

        $navigatorSummary = $this->buildNavigatorSummary($normalizedText, $matches);

        return [
            'keywords' => array_slice($keywords, 0, 20),
            'matches' => $matches,
            'navigator_summary' => $navigatorSummary,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $text): array
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text) ?? '');
        $parts = preg_split('/\s+/', $clean) ?: [];

        $stopWords = [
            'and', 'the', 'for', 'with', 'from', 'that', 'this', 'have', 'has', 'was', 'were',
            'job', 'role', 'work', 'experience', 'years', 'year', 'skills', 'profile',
            'city', 'state', 'india', 'remote',
        ];

        $keywords = [];

        foreach ($parts as $part) {
            $token = trim($part);
            if ($token === '' || mb_strlen($token) < 3 || in_array($token, $stopWords, true)) {
                continue;
            }
            $keywords[] = $token;
        }

        return array_values(array_unique($keywords));
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function jaccardScore(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $common = array_intersect($left, $right);
        $union = array_unique(array_merge($left, $right));

        if ($union === []) {
            return 0.0;
        }

        return count($common) / count($union);
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     */
    private function buildNavigatorSummary(string $cvText, array $matches): string
    {
        if ($matches === []) {
            return 'No close role matches found yet. Add more role-specific keywords (tech stack, domain, seniority) to improve matching.';
        }

        if (! (bool) setting('ai_enabled', false) || ! (bool) setting('ai_job_matching_enabled', true) || ! $this->gateway->shouldUseGemini()) {
            return 'Top roles ranked by CV keyword overlap. Focus first on listings above 55% match score and tailor your intro message with matched skills.';
        }

        $payload = json_encode([
            'task' => 'Generate a short recruiter-style CV guidance summary',
            'cv_excerpt' => mb_substr($cvText, 0, 1000),
            'matches' => $matches,
            'constraints' => [
                'max_words' => 90,
                'tone' => 'actionable and concise',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->gateway->completeText(
            'You are AI Navigator for job matching. Return plain text only.',
            (string) $payload,
            ['max_tokens' => 220, 'temperature' => 0.3]
        );

        $summary = trim((string) ($response['content'] ?? ''));

        return $summary !== ''
            ? $summary
            : 'Top roles ranked by CV keyword overlap. Focus first on listings above 55% match score and tailor your intro message with matched skills.';
    }
}
