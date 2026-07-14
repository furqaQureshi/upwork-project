<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnisellFeaturedAdsSeeder extends Seeder
{
    private const UNISELL_HOME_URL = 'https://www.unisell.in/';
    private const UNISELL_SEARCH_API_URL = 'https://www.unisell.in/api/relevance/v4/search';
    private const UNISELL_PAN_INDIA_LOCATION_ID = '1000001';
    private const UNISELL_CLIENT_VERSION = '11.31.1';
    private const DEFAULT_IMPORT_LIMIT = 500;
    private const MAX_IMPORT_LIMIT = 500;
    private const API_PAGE_SIZE = 50;
    private const API_MAX_PAGES_PER_CATEGORY = 40;
    private const HTTP_TIMEOUT_SECONDS = 8;

    /**
     * @var array<string, bool>
     */
    private array $allowedStatesMap = [];

    /**
     * Fetch live Unisell item cards and seed them as featured ads.
     */
    public function run(): void
    {
        $this->allowedStatesMap = $this->buildAllowedStatesMap(
            (string) env('UNISELL_ALLOWED_STATES', '')
        );

        $limit = min(
            self::MAX_IMPORT_LIMIT,
            max(1, (int) env('UNISELL_FEATURED_IMPORT_LIMIT', self::DEFAULT_IMPORT_LIMIT))
        );

        $this->command?->info("Starting Unisell pan-India import. Target ads: {$limit}");
        if (! empty($this->allowedStatesMap)) {
            $states = implode(', ', array_keys($this->allowedStatesMap));
            $this->command?->info("Applying state filter: {$states}");
        }

        $fallbackSeller = User::firstOrCreate(
            ['email' => 'unisell.import@unsell.test'],
            [
                'name' => 'Unisell Import Seller',
                'phone' => '+919900009999',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'email_verified_at' => now(),
                'password' => bcrypt('Seller@12345'),
            ]
        );

        $sellerCache = [
            'fallback' => $fallbackSeller,
        ];

        $categoryIds = Category::query()->pluck('id', 'name')->all();
        if (empty($categoryIds)) {
            $this->command?->warn('Unisell seeder skipped: categories are missing. Run CategorySeeder first.');

            return;
        }

        // Prefer official-ish Unisell web API payload because it is more stable than page scraping.
        $ads = $this->fetchUnisellApiAds($limit);

        // Fallback to page parsing only when API is unavailable.
        if (empty($ads)) {
            $itemUrls = $this->fetchUnisellItemUrls($limit);
            foreach ($itemUrls as $itemUrl) {
                $ad = $this->fetchUnisellAdPayload($itemUrl);
                if ($ad !== null) {
                    $ads[] = $ad;
                }
            }
        }

        $adsByExternalId = [];
        foreach ($ads as $ad) {
            $externalId = (int) ($ad['external_id'] ?? 0);
            if ($externalId > 0) {
                $adsByExternalId[$externalId] = $ad;
            }
        }

        $ads = array_slice(array_values($adsByExternalId), 0, $limit);
        $ads = $this->enrichAdsWithSellerContacts($ads);

        if (empty($ads)) {
            $this->command?->warn('Unisell seeder skipped: could not fetch ad data from Unisell.');

            return;
        }

        $imported = 0;

        foreach ($ads as $ad) {
            $categoryId = $this->resolveCategoryId(
                $categoryIds,
                $ad['title'],
                $ad['url'] ?? '',
                $ad['category_hint'] ?? null
            );
            $slug = 'unisell-featured-'.$ad['external_id'];
            $seller = $this->resolveSellerUser($ad, $fallbackSeller, $sellerCache);

            $listing = Listing::updateOrCreate(
                ['slug' => $slug],
                [
                    'user_id' => $seller->id,
                    'category_id' => $categoryId,
                    'title' => Str::limit($ad['title'], 140, ''),
                    'description' => Str::limit($ad['description'], 5000, ''),
                    'price' => $ad['price'],
                    'price_type' => $ad['price'] <= 0 ? 'free' : 'fixed',
                    'currency' => 'INR',
                    'condition' => $ad['condition'],
                    'city' => Str::limit($ad['city'], 120, ''),
                    'state' => $ad['state'] !== '' ? Str::limit($ad['state'], 120, '') : null,
                    'address' => $ad['address'] !== '' ? Str::limit($ad['address'], 255, '') : null,
                    'latitude' => null,
                    'longitude' => null,
                    'status' => 'approved',
                    'is_featured' => true,
                    'featured_until' => now()->addDays(30),
                    'views' => random_int(100, 1500),
                    'published_at' => now()->subDays(random_int(1, 20)),
                    'expires_at' => now()->addDays(60),
                    'youtube_url' => null,
                ]
            );

            if ($ad['image_url'] !== '') {
                $imagePath = $this->downloadImage($ad['image_url'], $ad['external_id']);
                if ($imagePath !== null) {
                    $this->upsertPrimaryImage($listing, $imagePath);
                }
            }

            $imported++;
        }

        $this->command?->info("Unisell featured ads import completed. Imported/updated: {$imported}");
    }

    /**
     * Try to extract featured cards first; if none found, fallback to regular Unisell item links.
     */
    private function fetchUnisellItemUrls(int $limit): array
    {
        $html = $this->getHtml(self::UNISELL_HOME_URL);
        if ($html === null || $html === '') {
            return [];
        }

        $featuredUrls = [];
        $allItemUrls = [];

        $links = $this->extractAnchors($html);
        foreach ($links as $anchor) {
            $url = $this->normalizeUnisellItemUrl($anchor['href']);
            if ($url === null) {
                continue;
            }

            $allItemUrls[] = $url;

            $text = Str::upper($anchor['text'].' '.$anchor['context']);
            if (str_contains($text, 'FEATURED')) {
                $featuredUrls[] = $url;
            }
        }

        // Fallback: pull URLs directly from raw HTML/script payload.
        foreach ($this->extractRawItemUrls($html) as $rawUrl) {
            $allItemUrls[] = $rawUrl;
        }

        $featuredUrls = array_values(array_unique($featuredUrls));
        $allItemUrls = array_values(array_unique($allItemUrls));

        // Extra fallback: try category pages when homepage does not expose enough cards.
        if (count($allItemUrls) < $limit) {
            foreach ($this->unisellFallbackCategoryUrls() as $fallbackUrl) {
                $fallbackHtml = $this->getHtml($fallbackUrl);
                if (! is_string($fallbackHtml) || $fallbackHtml === '') {
                    continue;
                }

                foreach ($this->extractRawItemUrls($fallbackHtml) as $rawUrl) {
                    $allItemUrls[] = $rawUrl;
                }

                $allItemUrls = array_values(array_unique($allItemUrls));
                if (count($allItemUrls) >= $limit) {
                    break;
                }
            }
        }

        if (count($featuredUrls) < $limit) {
            foreach ($allItemUrls as $url) {
                if (! in_array($url, $featuredUrls, true)) {
                    $featuredUrls[] = $url;
                }

                if (count($featuredUrls) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($featuredUrls, 0, $limit);
    }

    /**
     * Fetch live ads from Unisell web search API across key categories and pages.
     */
    private function fetchUnisellApiAds(int $limit): array
    {
        $ads = [];
        $definitions = $this->unisellApiCategoryDefinitions();
        if (empty($definitions)) {
            return [];
        }

        $limitPerCategory = max(1, (int) ceil($limit / count($definitions)));
        $seenExternalIds = [];

        foreach ($definitions as $definition) {
            if (count($ads) >= $limit) {
                break;
            }

            $remaining = $limit - count($ads);
            $maxCategoryCount = min($limitPerCategory, $remaining);
            $categoryFetched = 0;
            $page = 1;

            while (
                $categoryFetched < $maxCategoryCount
                && count($ads) < $limit
                && $page <= self::API_MAX_PAGES_PER_CATEGORY
            ) {
                $requestLimit = max(
                    1,
                    min(
                        self::API_PAGE_SIZE,
                        $maxCategoryCount - $categoryFetched,
                        $limit - count($ads)
                    )
                );

                $payload = $this->requestJson(self::UNISELL_SEARCH_API_URL, [
                    'category' => (int) $definition['unisell_category_id'],
                    'location' => self::UNISELL_PAN_INDIA_LOCATION_ID,
                    'limit' => $requestLimit,
                    'page' => $page,
                    'platform' => 'web-desktop',
                    'clientId' => 'pwa',
                    'clientVersion' => self::UNISELL_CLIENT_VERSION,
                ]);

                if (! is_array($payload)) {
                    break;
                }

                $rows = data_get($payload, 'data', []);
                if (! is_array($rows) || empty($rows)) {
                    break;
                }

                $addedThisPage = 0;

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $mappedAd = $this->mapApiAdRow($row, (string) $definition['local_category']);
                    if ($mappedAd === null) {
                        continue;
                    }

                    $externalId = (int) $mappedAd['external_id'];
                    if (isset($seenExternalIds[$externalId])) {
                        continue;
                    }

                    $seenExternalIds[$externalId] = true;
                    $ads[] = $mappedAd;
                    $addedThisPage++;
                    $categoryFetched++;

                    if ($categoryFetched >= $maxCategoryCount || count($ads) >= $limit) {
                        break;
                    }
                }

                $totalPages = (int) data_get($payload, 'metadata.total_pages', 0);
                if ($addedThisPage === 0) {
                    break;
                }

                if ($totalPages > 0 && $page >= $totalPages) {
                    break;
                }

                $page++;
            }
        }

        return $ads;
    }

    private function mapApiAdRow(array $row, string $categoryHint): ?array
    {
        $externalId = (int) data_get($row, 'ad_id', data_get($row, 'id', 0));
        if ($externalId <= 0) {
            return null;
        }

        $title = $this->cleanText((string) data_get($row, 'title', ''));
        if ($title === '') {
            return null;
        }

        $description = $this->cleanText((string) data_get($row, 'description', ''));
        if ($description === '') {
            $description = 'Imported from Unisell listing: '.$title;
        }

        $price = (float) data_get($row, 'price.value.raw', 0);
        if ($price <= 0) {
            $price = $this->extractPrice('', $title.' '.$description);
        }

        $city = $this->cleanText((string) data_get($row, 'locations_resolved.ADMIN_LEVEL_3_name', ''));
        $state = $this->cleanText((string) data_get($row, 'locations_resolved.ADMIN_LEVEL_1_name', ''));
        $area = $this->cleanText((string) data_get($row, 'locations_resolved.SUBLOCALITY_LEVEL_1_name', ''));

        if (! $this->passesStateFilter($state)) {
            return null;
        }

        if ($city === '') {
            $city = 'India';
        }

        $addressParts = array_values(array_filter([$area, $city, $state]));
        $address = $this->cleanText(implode(', ', $addressParts));

        $url = trim((string) data_get($row, 'url', ''));
        if ($url === '') {
            $url = 'https://www.unisell.in/item/iid-'.$externalId;
        } elseif (str_starts_with($url, '/')) {
            $url = 'https://www.unisell.in'.$url;
        }

        $sellerName = $this->cleanText((string) data_get($row, 'user_name', data_get($row, 'user.name', '')));
        $sellerExternalId = trim((string) data_get($row, 'user_id', data_get($row, 'user.id', '')));
        $sellerPhone = $this->extractSellerPhoneFromApiRow($row);
        $sellerHasPhoneOnUnisell = (bool) data_get($row, 'has_phone_param', false);

        return [
            'external_id' => $externalId,
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'image_url' => $this->extractApiImageUrl($row),
            'price' => $price,
            'condition' => $this->inferCondition($title, $description),
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'category_hint' => $categoryHint,
            'seller_external_id' => $sellerExternalId,
            'seller_name' => $sellerName,
            'seller_phone' => $sellerPhone,
            'seller_has_phone_on_unisell' => $sellerHasPhoneOnUnisell,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function buildAllowedStatesMap(string $rawStates): array
    {
        if (trim($rawStates) === '') {
            return [];
        }

        $states = array_filter(array_map(
            fn (string $state): string => Str::lower($this->cleanText($state)),
            explode(',', $rawStates)
        ));

        return array_fill_keys($states, true);
    }

    private function passesStateFilter(string $state): bool
    {
        if (empty($this->allowedStatesMap)) {
            return true;
        }

        if ($state === '') {
            return false;
        }

        return isset($this->allowedStatesMap[Str::lower($state)]);
    }

    private function enrichAdsWithSellerContacts(array $ads): array
    {
        $maxLookups = max(0, (int) env('UNISELL_CONTACT_LOOKUP_LIMIT', count($ads)));
        $requirePhoneFlag = (bool) env('UNISELL_REQUIRE_PHONE_FLAG', false);
        $lookups = 0;
        $captured = 0;

        foreach ($ads as $index => $ad) {
            if ($lookups >= $maxLookups) {
                break;
            }

            $existingPhone = trim((string) ($ad['seller_phone'] ?? ''));
            if ($existingPhone !== '') {
                continue;
            }

            if ($requirePhoneFlag && ! (bool) ($ad['seller_has_phone_on_unisell'] ?? false)) {
                continue;
            }

            $url = trim((string) ($ad['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $lookups++;
            $phone = $this->extractSellerPhoneFromItemPage($url);
            if ($phone === null) {
                continue;
            }

            $ads[$index]['seller_phone'] = $phone;
            $captured++;
        }

        if ($captured > 0) {
            $this->command?->info("Unisell contact enrichment captured {$captured} phone number(s).");
        }

        return $ads;
    }

    private function extractSellerPhoneFromItemPage(string $url): ?string
    {
        $html = $this->getHtml($url);
        if ($html === null || $html === '') {
            return null;
        }

        return $this->extractPhoneFromText($html);
    }

    private function extractSellerPhoneFromApiRow(array $row): ?string
    {
        $candidates = [
            data_get($row, 'phone'),
            data_get($row, 'mobile'),
            data_get($row, 'contact'),
            data_get($row, 'contact_phone'),
            data_get($row, 'user.phone'),
            data_get($row, 'user.contact'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = $this->normalizeIndianPhone($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function extractSellerNameFromHtml(string $html): string
    {
        $patterns = [
            '/"user_name"\s*:\s*"([^"\\]{2,120}(?:\\.[^"\\]{0,120})*)"/i',
            '/"sellerName"\s*:\s*"([^"\\]{2,120}(?:\\.[^"\\]{0,120})*)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $html, $matches)) {
                continue;
            }

            $decoded = html_entity_decode(stripcslashes((string) $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $name = $this->cleanText($decoded);
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    private function extractSellerExternalIdFromHtml(string $html): ?string
    {
        if (! preg_match('/"user_id"\s*:\s*"?(\d{3,20})"?/i', $html, $matches)) {
            return null;
        }

        return (string) $matches[1];
    }

    private function extractPhoneFromText(string $text): ?string
    {
        $jsonPatterns = [
            '/"(?:phone|mobile|contact(?:_?number)?|whatsapp(?:_?number)?)"\s*:\s*"([^"]{8,30})"/i',
            '/"(?:phone|mobile|contact(?:_?number)?|whatsapp(?:_?number)?)"\s*:\s*(\+?\d[\d\s-]{8,20})/i',
        ];

        foreach ($jsonPatterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }

            foreach ($matches[1] as $candidate) {
                $normalized = $this->normalizeIndianPhone((string) $candidate);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        if (preg_match_all('/(?<!\d)(?:\+91[\s-]?)?[6-9]\d{9}(?!\d)/', $text, $matches)) {
            foreach ($matches[0] as $candidate) {
                $normalized = $this->normalizeIndianPhone((string) $candidate);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function normalizeIndianPhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        $digits = ltrim($digits, '0');

        if (preg_match('/^(\d)\1{9,11}$/', $digits) === 1) {
            return null;
        }

        if (in_array($digits, ['6666666667', '7777777777', '8888888888', '9999999999'], true)) {
            return null;
        }

        if (preg_match('/^[6-9]\d{9}$/', $digits) === 1) {
            return '+91'.$digits;
        }

        if (preg_match('/^91[6-9]\d{9}$/', $digits) === 1) {
            return '+'.$digits;
        }

        return null;
    }

    private function resolveSellerUser(array $ad, User $fallbackSeller, array &$sellerCache): User
    {
        $sellerName = $this->cleanText((string) ($ad['seller_name'] ?? ''));
        $sellerExternalIdRaw = preg_replace('/\D+/', '', (string) ($ad['seller_external_id'] ?? ''));
        $sellerExternalId = is_string($sellerExternalIdRaw) ? $sellerExternalIdRaw : '';
        $sellerPhone = $this->normalizeIndianPhone((string) ($ad['seller_phone'] ?? ''));

        if ($sellerName === '' && $sellerExternalId === '' && $sellerPhone === null) {
            return $fallbackSeller;
        }

        $city = $this->cleanText((string) ($ad['city'] ?? ''));
        $state = $this->cleanText((string) ($ad['state'] ?? ''));

        $cacheKey = $sellerExternalId !== ''
            ? 'seller:'.$sellerExternalId
            : 'seller:'.md5(Str::lower($sellerName.'|'.$city.'|'.$state.'|'.($sellerPhone ?? '')));

        if (isset($sellerCache[$cacheKey]) && $sellerCache[$cacheKey] instanceof User) {
            return $sellerCache[$cacheKey];
        }

        if ($sellerName === '') {
            $sellerName = $sellerExternalId !== '' ? 'Unisell Seller '.$sellerExternalId : 'Unisell Seller';
        }

        $emailLocalPart = $sellerExternalId !== ''
            ? 'unisell-seller-'.$sellerExternalId
            : 'unisell-seller-'.substr(md5(Str::lower($sellerName.'|'.$city.'|'.$state)), 0, 16);

        $seller = User::updateOrCreate(
            ['email' => $emailLocalPart.'@unisell.import.local'],
            [
                'name' => Str::limit($sellerName, 120, ''),
                'phone' => $sellerPhone,
                'city' => $city !== '' ? Str::limit($city, 120, '') : null,
                'state' => $state !== '' ? Str::limit($state, 120, '') : null,
                'email_verified_at' => now(),
                'password' => bcrypt('Seller@12345'),
            ]
        );

        $sellerCache[$cacheKey] = $seller;

        return $seller;
    }

    private function unisellApiCategoryDefinitions(): array
    {
        return [
            ['unisell_category_id' => 84, 'local_category' => 'Cars'],
            ['unisell_category_id' => 81, 'local_category' => 'Bikes'],
            ['unisell_category_id' => 1453, 'local_category' => 'Mobiles'],
            ['unisell_category_id' => 1523, 'local_category' => 'Electronics'],
            ['unisell_category_id' => 1725, 'local_category' => 'Property'],
            ['unisell_category_id' => 1793, 'local_category' => 'Fashion'],
        ];
    }

    private function extractApiImageUrl(array $row): string
    {
        $candidates = [
            data_get($row, 'images.0.url'),
            data_get($row, 'images.0.full.url'),
            data_get($row, 'images.0.big.url'),
        ];

        foreach ($candidates as $candidate) {
            $imageUrl = trim((string) ($candidate ?? ''));
            if ($imageUrl === '') {
                continue;
            }

            if (str_starts_with($imageUrl, '//')) {
                $imageUrl = 'https:'.$imageUrl;
            }

            if (str_starts_with($imageUrl, 'https://') || str_starts_with($imageUrl, 'http://')) {
                return $imageUrl;
            }
        }

        return '';
    }

    private function extractRawItemUrls(string $html): array
    {
        $normalizedHtml = str_replace(['\\/', '\\u002F'], ['/', '/'], $html);
        $patterns = [
            '~https://www\.unisell\.in/item/[^\s"\'\<\>]+iid-\d+~i',
            '~https://unisell\.in/item/[^\s"\'\<\>]+iid-\d+~i',
            '~/item/[^\s"\'\<\>]+iid-\d+~i',
        ];

        $collected = [];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $normalizedHtml, $matches)) {
                continue;
            }

            foreach ($matches[0] as $candidate) {
                $url = $this->normalizeUnisellItemUrl($candidate);
                if ($url !== null) {
                    $collected[] = $url;
                }
            }
        }

        return array_values(array_unique($collected));
    }

    private function unisellFallbackCategoryUrls(): array
    {
        return [
            'https://www.unisell.in/cars_c84',
            'https://www.unisell.in/motorcycles_c81',
            'https://www.unisell.in/mobile-phones_c1453',
            'https://www.unisell.in/tvs-video-audio_c1523',
            'https://www.unisell.in/beds-wardrobes_c1591',
            'https://www.unisell.in/for-sale-houses-apartments_c1725',
            'https://www.unisell.in/men_c1793',
        ];
    }

    /**
     * Pull ad details from a listing page.
     */
    private function fetchUnisellAdPayload(string $url): ?array
    {
        $html = $this->getHtml($url);
        if ($html === null || $html === '') {
            return null;
        }

        $externalId = $this->extractExternalId($url);
        if ($externalId === null) {
            return null;
        }

        $title = $this->extractMeta($html, 'og:title');
        if ($title === '') {
            $title = $this->extractHeading($html);
        }

        if ($title === '') {
            return null;
        }

        $description = $this->extractMeta($html, 'og:description');
        if ($description === '') {
            $description = 'Imported from Unisell listing: '.$title;
        }

        $imageUrl = $this->extractMeta($html, 'og:image');
        if ($imageUrl === '') {
            $imageUrl = $this->extractFirstImageUrl($html);
        }

        [$address, $city, $state] = $this->extractLocationParts($html, $url);
        $price = $this->extractPrice($html, $title.' '.$description);
        $condition = $this->inferCondition($title, $description);
        $sellerName = $this->extractSellerNameFromHtml($html);
        $sellerPhone = $this->extractPhoneFromText($html);
        $sellerExternalId = $this->extractSellerExternalIdFromHtml($html);

        return [
            'external_id' => $externalId,
            'url' => $url,
            'title' => $this->cleanText($title),
            'description' => $this->cleanText($description),
            'image_url' => trim($imageUrl),
            'price' => $price,
            'condition' => $condition,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'category_hint' => null,
            'seller_external_id' => $sellerExternalId,
            'seller_name' => $sellerName,
            'seller_phone' => $sellerPhone,
            'seller_has_phone_on_unisell' => $sellerPhone !== null,
        ];
    }

    private function extractAnchors(string $html): array
    {
        $anchors = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (! @$dom->loadHTML($html)) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim((string) $node->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $text = $this->cleanText((string) $node->textContent);
            $context = '';
            if ($node->parentNode !== null) {
                $context = $this->cleanText((string) $node->parentNode->textContent);
            }

            $anchors[] = [
                'href' => $href,
                'text' => $text,
                'context' => $context,
            ];
        }

        return $anchors;
    }

    private function normalizeUnisellItemUrl(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, '/item/')) {
            $href = 'https://www.unisell.in'.$href;
        }

        if (! str_starts_with($href, 'https://www.unisell.in/item/')) {
            return null;
        }

        if (! preg_match('~iid-(\d+)~i', $href)) {
            return null;
        }

        return strtok($href, '?') ?: $href;
    }

    private function extractExternalId(string $url): ?int
    {
        if (! preg_match('~iid-(\d+)~i', $url, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function extractMeta(string $html, string $property): string
    {
        $quotedProperty = preg_quote($property, '/');
        $patterns = [
            '/<meta[^>]+property=["\']'.$quotedProperty.'["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']'.$quotedProperty.'["\'][^>]*>/i',
            '/<meta[^>]+name=["\']'.$quotedProperty.'["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']'.$quotedProperty.'["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return '';
    }

    private function extractHeading(string $html): string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return $this->cleanText(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    private function extractFirstImageUrl(string $html): string
    {
        if (preg_match('~https://apollo\.unisell\.in/v1/files/[^"\'\s>]+~i', $html, $matches)) {
            return trim($matches[0]);
        }

        return '';
    }

    private function extractLocationParts(string $html, string $url): array
    {
        $plainText = $this->cleanText(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $address = '';
        if (preg_match('/Posted in\s+(.+?)\s+AD ID\s+/i', $plainText, $matches)) {
            $address = $this->cleanText($matches[1]);
        }

        if ($address === '') {
            $address = $this->addressFromUrl($url);
        }

        $city = 'Mumbai';
        $state = 'Maharashtra';

        if ($address !== '') {
            $parts = array_values(array_filter(array_map(
                static fn (string $part): string => trim($part),
                explode(',', $address)
            )));

            if (count($parts) >= 1) {
                $city = Str::limit($parts[count($parts) - 1], 120, '');
            }

            if (count($parts) >= 2) {
                $state = Str::limit($parts[count($parts) - 2], 120, '');
            }
        }

        return [$address, $city, $state];
    }

    private function addressFromUrl(string $url): string
    {
        if (! preg_match('~-in-([a-z0-9\-]+)-iid-\d+~i', $url, $matches)) {
            return '';
        }

        return Str::title(str_replace('-', ' ', $matches[1]));
    }

    private function extractPrice(string $html, string $fallbackText): float
    {
        $plainText = $this->cleanText(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')).' '.$fallbackText;

        if (preg_match('/(?:INR|Rs\.?|\x{20B9})\s*([0-9][0-9,\.]{1,15})/iu', $plainText, $matches)) {
            $price = (float) str_replace([',', ' '], '', $matches[1]);

            if ($price > 0) {
                return round($price, 2);
            }
        }

        if (preg_match('/\b([0-9]{3,9})\b/', $plainText, $matches)) {
            $price = (float) $matches[1];
            if ($price > 0) {
                return round($price, 2);
            }
        }

        return 9999.00;
    }

    private function inferCondition(string $title, string $description): string
    {
        $haystack = Str::lower($title.' '.$description);

        if (str_contains($haystack, 'brand new') || preg_match('/\bnew\b/i', $haystack) === 1) {
            return 'new';
        }

        if (str_contains($haystack, 'refurb')) {
            return 'refurbished';
        }

        return 'used';
    }

    private function resolveCategoryId(array $categoryIds, string $title, string $url, ?string $categoryHint = null): int
    {
        if (is_string($categoryHint) && $categoryHint !== '' && isset($categoryIds[$categoryHint])) {
            return (int) $categoryIds[$categoryHint];
        }

        $haystack = Str::lower($title.' '.$url);

        $map = [
            'Cars' => ['car', 'scorpio', 'sedan', 'suv', 'hatchback'],
            'Bikes' => ['bike', 'motorcycle', 'scooter', 'royal enfield', 'yamaha'],
            'Mobiles' => ['mobile', 'iphone', 'smartphone', 'phone', 'samsung', 'oneplus'],
            'Electronics' => ['electronics', 'tv', 'laptop', 'camera', 'projector', 'appliance', 'audio'],
            'Furniture' => ['furniture', 'bed', 'wardrobe', 'sofa', 'table', 'chair'],
            'Property' => ['property', 'apartment', 'flat', 'house', 'plot', 'land', 'rent', 'shop'],
            'Fashion' => ['fashion', 'shirt', 'jeans', 'saree', 'watch', 'dress', 'shoe', 'men-c1793', 'women-c1795'],
            'Jobs' => ['job', 'hiring', 'vacancy'],
            'Commercial Vehicles' => ['commercial', 'truck', 'bus', 'tractor'],
        ];

        foreach ($map as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword) && isset($categoryIds[$categoryName])) {
                    return (int) $categoryIds[$categoryName];
                }
            }
        }

        if (isset($categoryIds['Electronics'])) {
            return (int) $categoryIds['Electronics'];
        }

        return (int) reset($categoryIds);
    }

    private function requestJson(string $url, array $query = []): ?array
    {
        try {
            $response = Http::withHeaders($this->apiHeaders())
                ->withOptions($this->httpOptions())
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($url, $query);

            if ($response->successful()) {
                $decoded = $response->json();
                if (is_array($decoded)) {
                    return $decoded;
                }

                $decoded = json_decode($response->body(), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable) {
        }

        $queryString = http_build_query($query);
        $fullUrl = $queryString !== '' ? $url.'?'.$queryString : $url;

        $headers = [
            'Accept: application/json',
            'Accept-Language: en-IN,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Referer: '.self::UNISELL_HOME_URL,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => self::HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $raw = @file_get_contents($fullUrl, false, $context);
        if (! is_string($raw) || $raw === '') {
            return $this->requestJsonViaCurlCli($fullUrl);
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $this->requestJsonViaCurlCli($fullUrl);
    }

    private function requestJsonViaCurlCli(string $url): ?array
    {
        $raw = $this->requestViaCurlCli($url);
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function requestViaCurlCli(string $url): ?string
    {
        $args = [
            '--http1.1',
            '--silent',
            '--show-error',
            '--location',
            '--max-time',
            (string) self::HTTP_TIMEOUT_SECONDS,
            '--user-agent',
            'curl/8.0',
            $url,
        ];

        foreach (['curl.exe', 'curl'] as $binary) {
            try {
                $result = Process::timeout(self::HTTP_TIMEOUT_SECONDS + 5)
                    ->run(array_merge([$binary], $args));

                if (! $result->successful()) {
                    continue;
                }

                $output = trim($result->output());
                if ($output !== '') {
                    return $output;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Language' => 'en-IN,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Referer' => self::UNISELL_HOME_URL,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        ];
    }

    private function downloadImage(string $imageUrl, int $externalId): ?string
    {
        try {
            $response = Http::withHeaders($this->browserHeaders())
                ->withOptions($this->httpOptions())
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($imageUrl);

            if (! $response->successful()) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if (! str_starts_with($contentType, 'image/')) {
                return null;
            }

            $extension = $this->extensionFromContentType($contentType);
            $path = 'listings/seeded/unisell/'.$externalId.'-'.Str::random(8).'.'.$extension;

            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function upsertPrimaryImage(Listing $listing, string $path): void
    {
        /** @var ListingImage|null $primary */
        $primary = $listing->images()->orderByDesc('is_primary')->orderBy('sort_order')->first();

        if ($primary !== null) {
            if ($primary->path !== $path && str_starts_with($primary->path, 'listings/seeded/unisell/')) {
                Storage::disk('public')->delete($primary->path);
            }

            $primary->update([
                'path' => $path,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            return;
        }

        $listing->images()->create([
            'path' => $path,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    private function extensionFromContentType(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };
    }

    private function getHtml(string $url): ?string
    {
        try {
            $response = Http::withHeaders($this->browserHeaders())
                ->withOptions($this->httpOptions())
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Throwable) {
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-IN,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Referer: '.self::UNISELL_HOME_URL,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => self::HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        return $this->requestViaCurlCli($url);
    }

    private function browserHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-IN,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Referer' => self::UNISELL_HOME_URL,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        ];
    }

    private function httpOptions(): array
    {
        $options = ['verify' => false];

        if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_1_1')) {
            $options['curl'] = [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ];
        }

        return $options;
    }

    private function cleanText(string $value): string
    {
        $cleaned = preg_replace('/\s+/u', ' ', trim($value));

        return is_string($cleaned) ? $cleaned : '';
    }
}
