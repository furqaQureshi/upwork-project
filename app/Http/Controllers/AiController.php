<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use App\Models\SubscriptionPackagePurchase;
use App\Services\AI\AiListingAssistantService;
use App\Services\AI\AutoIqService;
use App\Services\AI\CompassGptService;
use App\Services\AI\CvMatchingService;
use App\Services\AI\LanguageGatewayService;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use ZipArchive;

class AiController extends Controller
{
    public function compass(): View|RedirectResponse
    {
        if (! $this->featureEnabled('ai_compass_enabled')) {
            return redirect()->route('home')->with('status', 'CompassGPT is currently disabled by admin.');
        }

        return view('ai.compass');
    }

    public function autoiq(Request $request, AutoIqService $autoIqService): View|RedirectResponse
    {
        if (! $this->featureEnabled('ai_autoiq_enabled')) {
            return redirect()->route('home')->with('status', 'AutoIQ is currently disabled by admin.');
        }

        return view('ai.autoiq', [
            'dashboard' => $autoIqService->dashboardForDealer($request->user()),
        ]);
    }

    public function navigator(): View|RedirectResponse
    {
        if (! $this->featureEnabled('ai_job_matching_enabled')) {
            return redirect()->route('home')->with('status', 'AI Navigator is currently disabled by admin.');
        }

        return view('ai.navigator');
    }

    public function generateListingDraft(
        Request $request,
        AiListingAssistantService $listingAssistantService,
        SubscriptionEntitlementService $entitlementService
    ): JsonResponse
    {
        if (! $this->featureEnabled('ai_listing_assistant_enabled')) {
            return response()->json([
                'ok' => false,
                'message' => 'AI listing assistant is disabled.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'condition' => ['nullable', 'string', 'in:new,used,refurbished'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $categoryName = '';
        if (! empty($validated['category_id'])) {
            $categoryName = (string) Category::query()
                ->where('id', (int) $validated['category_id'])
                ->value('name');
        }

        $payload = $validated;
        $payload['category_name'] = $categoryName;

        $images = (array) $request->file('images', []);
        $result = $listingAssistantService->generateDraft($payload, $images);

        $consumedPurchase = $this->consumeAiCredit(
            $request,
            $entitlementService,
            'ai_listing_draft',
            [
                'category_id' => $validated['category_id'] ?? null,
                'images_count' => count($images),
            ]
        );

        if (! $consumedPurchase) {
            return $this->aiQuotaErrorResponse();
        }

        return response()->json([
            'ok' => true,
            'data' => $result,
            'usage' => $this->aiUsagePayload($consumedPurchase),
        ]);
    }

    public function recommendPrice(
        Request $request,
        AiListingAssistantService $listingAssistantService,
        SubscriptionEntitlementService $entitlementService
    ): JsonResponse
    {
        if (! $this->featureEnabled('ai_price_recommendation_enabled')) {
            return response()->json([
                'ok' => false,
                'message' => 'AI price recommendation is disabled.',
            ], 403);
        }

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'condition' => ['nullable', 'string', 'in:new,used,refurbished'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);

        $recommendation = $listingAssistantService->recommendPrice($validated);

        $consumedPurchase = $this->consumeAiCredit(
            $request,
            $entitlementService,
            'ai_price_recommendation',
            [
                'category_id' => $validated['category_id'] ?? null,
                'city' => $validated['city'] ?? null,
            ]
        );

        if (! $consumedPurchase) {
            return $this->aiQuotaErrorResponse();
        }

        return response()->json([
            'ok' => true,
            'data' => $recommendation,
            'usage' => $this->aiUsagePayload($consumedPurchase),
        ]);
    }

    public function compassChat(
        Request $request,
        CompassGptService $compassGptService,
        LanguageGatewayService $languageGatewayService
    ): JsonResponse
    {
        if (! $this->featureEnabled('ai_compass_enabled')) {
            return response()->json([
                'ok' => false,
                'message' => 'CompassGPT is disabled.',
            ], 403);
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'ui_language' => ['nullable', 'string', 'max:32'],
            'history' => ['nullable', 'array'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant,system'],
            'history.*.content' => ['required_with:history', 'string', 'max:1200'],
        ]);

        $history = array_values(array_map(static function (array $turn): array {
            return [
                'role' => (string) ($turn['role'] ?? 'user'),
                'content' => (string) ($turn['content'] ?? ''),
            ];
        }, (array) ($validated['history'] ?? [])));

        $inbound = $languageGatewayService->prepareInbound(
            query: (string) $validated['query'],
            history: $history,
            locationLabel: trim((string) ($validated['location_label'] ?? '')),
            uiLanguage: trim((string) ($validated['ui_language'] ?? 'auto'))
        );

        $response = $compassGptService->chat(
            (string) ($inbound['query'] ?? $validated['query']),
            (array) ($inbound['history'] ?? $history),
            [
                'location_label' => trim((string) ($inbound['location_label'] ?? ($validated['location_label'] ?? ''))),
            ]
        );

        $response['recommendations'] = array_map(static function (array $item): array {
            $slug = (string) ($item['slug'] ?? '');
            if ($slug !== '') {
                $item['listing_url'] = route('listings.show', $slug);
            }

            return $item;
        }, (array) ($response['recommendations'] ?? []));

        $response = $languageGatewayService->localizeOutbound(
            $response,
            (string) ($inbound['response_language'] ?? 'en')
        );

        $response['detected_language'] = (string) ($inbound['detected_language'] ?? 'en');
        $response['translation'] = [
            'inbound' => (bool) ($inbound['translated_inbound'] ?? false),
            'outbound' => (bool) ($response['translated_outbound'] ?? false),
            'provider' => (string) ($inbound['translation_provider'] ?? 'local'),
        ];

        return response()->json([
            'ok' => true,
            'data' => $response,
        ]);
    }

    public function cvMatch(
        Request $request,
        CvMatchingService $cvMatchingService,
        SubscriptionEntitlementService $entitlementService
    ): JsonResponse
    {
        if (! $this->featureEnabled('ai_job_matching_enabled')) {
            return response()->json([
                'ok' => false,
                'message' => 'AI CV matching is disabled.',
            ], 403);
        }

        $validated = $request->validate([
            'cv_text' => ['nullable', 'string', 'max:20000'],
            'cv_file' => ['nullable', 'file', 'mimes:txt,md,csv,docx,pdf', 'max:8192'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $cvText = trim((string) ($validated['cv_text'] ?? ''));

        if ($cvText === '' && $request->hasFile('cv_file')) {
            $cvText = $this->extractTextFromCvFile($request->file('cv_file'));
        }

        if ($cvText === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Provide CV text or upload a text-readable CV file.',
            ], 422);
        }

        $result = $cvMatchingService->match($cvText, (int) ($validated['limit'] ?? 8));

        $result['matches'] = array_map(static function (array $match): array {
            $slug = (string) ($match['slug'] ?? '');
            if ($slug !== '') {
                $match['listing_url'] = route('listings.show', $slug);
            }

            return $match;
        }, (array) ($result['matches'] ?? []));

        $consumedPurchase = $this->consumeAiCredit(
            $request,
            $entitlementService,
            'ai_cv_match',
            [
                'cv_text_length' => strlen($cvText),
                'limit' => (int) ($validated['limit'] ?? 8),
            ]
        );

        if (! $consumedPurchase) {
            return $this->aiQuotaErrorResponse();
        }

        return response()->json([
            'ok' => true,
            'data' => $result,
            'usage' => $this->aiUsagePayload($consumedPurchase),
        ]);
    }

    public function similarListings(Request $request, Listing $listing): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = (int) ($validated['limit'] ?? 6);

        $items = Listing::query()
            ->approved()
            ->with(['images', 'category', 'user'])
            ->where('id', '!=', $listing->id)
            ->where('category_id', $listing->category_id)
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(static function (Listing $item): array {
                return [
                    'listing_id' => $item->id,
                    'title' => $item->title,
                    'price' => (float) $item->price,
                    'city' => $item->city,
                    'listing_url' => route('listings.show', $item),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    private function featureEnabled(string $featureKey): bool
    {
        return (bool) setting($featureKey, true);
    }

    private function consumeAiCredit(
        Request $request,
        SubscriptionEntitlementService $entitlementService,
        string $usageType,
        array $meta = []
    ): ?SubscriptionPackagePurchase
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $purchase = $entitlementService->findUsableAiPurchase($user);

        if (! $purchase) {
            return null;
        }

        return $entitlementService->consumeAiPurchase($purchase, $usageType, null, $meta);
    }

    private function aiQuotaErrorResponse(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'AI credits are exhausted. Buy or renew a package with AI access to continue.',
        ], 403);
    }

    private function aiUsagePayload(?SubscriptionPackagePurchase $purchase): array
    {
        if (! $purchase) {
            return [];
        }

        return [
            'purchase_id' => $purchase->id,
            'remaining_ai_items' => $purchase->remaining_ai_items,
            'is_unlimited' => $purchase->subscriptionPackage?->ai_usage_limit_type === 'unlimited',
        ];
    }

    private function extractTextFromCvFile(?UploadedFile $file): string
    {
        if (! $file) {
            return '';
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['txt', 'md', 'csv'], true)) {
            $raw = @file_get_contents($file->getRealPath() ?: '');

            return is_string($raw) ? trim($raw) : '';
        }

        if ($extension === 'docx') {
            $zip = new ZipArchive();
            $path = $file->getRealPath() ?: '';
            if ($path !== '' && $zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml') ?: '';
                $zip->close();

                if ($xml !== '') {
                    $text = preg_replace('/<[^>]+>/', ' ', $xml) ?? '';
                    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

                    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
                }
            }

            return '';
        }

        if ($extension === 'pdf') {
            // PDF extraction is intentionally conservative without external parsers.
            return '';
        }

        return '';
    }
}
