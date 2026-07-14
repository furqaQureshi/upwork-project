<?php

namespace App\Services\AI;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PersonalizationService
{
    public function applyFeedOrdering(Builder $query, ?User $user): void
    {
        if (! (bool) setting('ai_enabled', false) || ! (bool) setting('ai_personalization_enabled', true) || ! $user) {
            return;
        }

        $preferredCategoryIds = $this->preferredCategoryIds($user);

        if ($preferredCategoryIds === []) {
            return;
        }

        $safeIds = implode(',', array_map('intval', $preferredCategoryIds));
        $query->orderByRaw('CASE WHEN category_id IN ('.$safeIds.') THEN 1 ELSE 0 END DESC');
    }

    /**
     * @return array<int, int>
     */
    public function preferredCategoryIds(User $user): array
    {
        $fromFavorites = $user->favoriteListings()
            ->pluck('listings.category_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $fromConversations = $user->conversationsAsBuyer()
            ->join('listings', 'conversations.listing_id', '=', 'listings.id')
            ->pluck('listings.category_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $fromOwnListings = $user->listings()
            ->pluck('category_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $all = array_values(array_filter(array_merge($fromFavorites, $fromConversations, $fromOwnListings)));

        if ($all === []) {
            return [];
        }

        $counts = array_count_values($all);
        arsort($counts);

        return array_slice(array_map('intval', array_keys($counts)), 0, 6);
    }

    /**
     * @return Collection<int, Listing>
     */
    public function similarListings(Listing $listing, int $limit = 6): Collection
    {
        $candidates = Listing::query()
            ->approved()
            ->with(['images', 'category', 'user'])
            ->where('id', '!=', $listing->id)
            ->where(function (Builder $builder) use ($listing): void {
                $builder
                    ->where('category_id', $listing->category_id)
                    ->orWhere('city', $listing->city)
                    ->orWhere('state', $listing->state)
                    ->orWhere('title', 'like', '%'.mb_substr($listing->title, 0, 24).'%');
            })
            ->latest('published_at')
            ->limit(80)
            ->get();

        $titleTokens = $this->tokenize($listing->title.' '.$listing->description);

        $scored = $candidates->map(function (Listing $candidate) use ($listing, $titleTokens): array {
            $score = 0.0;

            if ($candidate->category_id === $listing->category_id) {
                $score += 45;
            }

            if (strcasecmp((string) $candidate->city, (string) $listing->city) === 0) {
                $score += 30;
            } elseif (
                $listing->state !== null
                && $candidate->state !== null
                && strcasecmp((string) $candidate->state, (string) $listing->state) === 0
            ) {
                $score += 12;
            } else {
                $score -= 18;
            }

            $score += $this->proximityScore($listing, $candidate);

            $candidateTokens = $this->tokenize($candidate->title.' '.$candidate->description);
            $overlap = $this->tokenOverlap($titleTokens, $candidateTokens);
            $score += $overlap * 30;

            $priceA = (float) $listing->price;
            $priceB = (float) $candidate->price;

            if ($priceA > 0 && $priceB > 0) {
                $distance = abs($priceA - $priceB) / max($priceA, $priceB);
                $score += max(0, 12 - ($distance * 20));
            }

            return [
                'listing' => $candidate,
                'score' => (float) $score,
            ];
        })->sortByDesc('score')->values();

        return $scored
            ->take(max(1, $limit))
            ->map(static fn (array $item): Listing => $item['listing'])
            ->values();
    }

    private function proximityScore(Listing $source, Listing $candidate): float
    {
        $sourceLat = $source->latitude !== null ? (float) $source->latitude : null;
        $sourceLng = $source->longitude !== null ? (float) $source->longitude : null;
        $candidateLat = $candidate->latitude !== null ? (float) $candidate->latitude : null;
        $candidateLng = $candidate->longitude !== null ? (float) $candidate->longitude : null;

        if ($sourceLat === null || $sourceLng === null || $candidateLat === null || $candidateLng === null) {
            return 0.0;
        }

        $distanceKm = $this->distanceKm($sourceLat, $sourceLng, $candidateLat, $candidateLng);

        if ($distanceKm <= 10) {
            return 28.0;
        }

        if ($distanceKm <= 25) {
            return 22.0;
        }

        if ($distanceKm <= 50) {
            return 15.0;
        }

        if ($distanceKm <= 100) {
            return 8.0;
        }

        if ($distanceKm <= 200) {
            return 2.0;
        }

        return -20.0;
    }

    private function distanceKm(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $latDelta = deg2rad($latB - $latA);
        $lngDelta = deg2rad($lngB - $lngA);
        $originLat = deg2rad($latA);
        $targetLat = deg2rad($latB);

        $haversine = sin($latDelta / 2) ** 2
            + cos($originLat) * cos($targetLat) * sin($lngDelta / 2) ** 2;

        $arc = 2 * asin(min(1.0, sqrt($haversine)));

        return 6371.0 * $arc;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text) ?? '');
        $parts = preg_split('/\s+/', $clean) ?: [];

        $stop = [
            'the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'you', 'our',
            'near', 'city', 'state', 'item', 'sale', 'sell', 'best', 'good',
        ];

        $tokens = [];
        foreach ($parts as $part) {
            $token = trim($part);
            if ($token === '' || mb_strlen($token) < 3 || in_array($token, $stop, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function tokenOverlap(array $left, array $right): float
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
}
