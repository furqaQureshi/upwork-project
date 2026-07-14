<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\SellerVerification;
use App\Models\SellerVerificationDocument;
use App\Models\SubscriptionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerVerificationController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSeller = $user->hasSellerVerificationAccess();

        $packages = SubscriptionPackage::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_seller_verification', true)
            ->orderBy('final_price')
            ->orderBy('name')
            ->get();

        $paidPackageIds = $user->subscriptionPackagePurchases()
            ->where('status', 'paid')
            ->pluck('subscription_package_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $latestVerification = $user->sellerVerifications()
            ->with(['documents', 'category', 'subscriptionPackage'])
            ->latest('id')
            ->first();

        $carVerification = $user->sellerVerifications()
            ->with(['documents', 'category', 'subscriptionPackage'])
            ->whereHas('subscriptionPackage', function ($query): void {
                $query->where('seller_tier', 'car_verified');
            })
            ->latest('id')
            ->first();

        $currentVerification = $user->primaryApprovedSellerVerification();

        return response()->json([
            'data' => [
                'is_seller' => $isSeller,
                'is_seller_verified' => (bool) $user->is_seller_verified,
                'is_car_seller_verified' => (bool) $user->isCarSellerVerified(),
                'is_premium_seller_verified' => (bool) $user->isPremiumSellerVerified(),
                'seller_verification_status' => $user->seller_verification_status,
                'car_seller_verification_status' => $carVerification?->status,
                'seller_type' => (string) ($user->seller_type ?? ''),
                'seller_badge_label' => $user->sellerBadgeLabel(),
                'latest_verification' => $latestVerification
                    ? $this->serializeVerification($latestVerification)
                    : null,
                'car_verification' => $carVerification
                    ? $this->serializeVerification($carVerification)
                    : null,
                'current_package' => $currentVerification?->subscriptionPackage
                    ? $this->serializePackage($currentVerification->subscriptionPackage)
                    : null,
                'paid_package_ids' => $paidPackageIds,
                'verification_packages' => $packages
                    ->map(fn (SubscriptionPackage $package): array => $this->serializePackage($package))
                    ->values(),
            ],
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'subscription_package_id' => [
                'required',
                'exists:subscription_packages,id',
                function ($attribute, $value, $fail): void {
                    $package = SubscriptionPackage::query()->find((int) $value);
                    if (! $package || ! $package->is_seller_verification || ! $package->is_active) {
                        $fail('Please select an active seller verification package.');
                    }
                },
            ],
            'company_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aadhar' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aadhar_number' => ['nullable', 'string', 'regex:/^\d{12}$/'],
            'pan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'pan_number' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
        ], [
            'aadhar_number.regex' => 'Aadhar number must be 12 digits.',
            'pan_number.regex' => 'PAN must be in valid format (e.g., ABCDE1234F).',
        ]);

        $hasCompany = $request->hasFile('company_certificate');
        $hasAadhar = $request->hasFile('aadhar');
        $hasPan = $request->hasFile('pan');
        $selectedDocs = (int) $hasCompany + (int) $hasAadhar + (int) $hasPan;

        if ($selectedDocs !== 1) {
            return response()->json([
                'message' => 'Upload exactly one document: PAN, Aadhar, or Company/Firm Certificate.',
                'errors' => [
                    'documents' => ['Upload exactly one document: PAN, Aadhar, or Company/Firm Certificate.'],
                ],
            ], 422);
        }

        if ($hasAadhar && ! preg_match('/^\d{12}$/', (string) $request->input('aadhar_number'))) {
            return response()->json([
                'message' => 'Aadhar number must be 12 digits.',
                'errors' => [
                    'aadhar_number' => ['Aadhar number must be 12 digits.'],
                ],
            ], 422);
        }

        if ($hasPan && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper((string) $request->input('pan_number')))) {
            return response()->json([
                'message' => 'PAN must be in valid format (e.g., ABCDE1234F).',
                'errors' => [
                    'pan_number' => ['PAN must be in valid format (e.g., ABCDE1234F).'],
                ],
            ], 422);
        }

        $package = SubscriptionPackage::query()->findOrFail((int) $validated['subscription_package_id']);

        $hasPaidPackage = $user->subscriptionPackagePurchases()
            ->where('subscription_package_id', $package->id)
            ->where('status', 'paid')
            ->exists();

        if (! $hasPaidPackage) {
            return response()->json([
                'message' => 'Please buy this seller verification package before submitting documents.',
            ], 422);
        }

        $existingApproved = $user->sellerVerifications()
            ->where('status', 'approved')
            ->whereHas('subscriptionPackage', function ($query) use ($package): void {
                $query->where('seller_tier', $package->seller_tier);
            })
            ->when($package->category_scope !== 'global', function ($query) use ($package): void {
                $query->where('category_id', $package->category_id);
            })
            ->exists();

        if ($existingApproved) {
            return response()->json([
                'message' => 'You already have an approved seller verification for this category.',
            ], 422);
        }

        $verification = SellerVerification::query()->create([
            'user_id' => $user->id,
            'category_id' => $package->category_id,
            'subscription_package_id' => $package->id,
            'status' => 'pending',
        ]);

        $user->forceFill([
            'seller_verification_status' => 'pending',
            'seller_verification_note' => null,
        ])->saveQuietly();

        $documents = [
            'company_certificate' => ['company_certificate', null],
            'aadhar' => ['aadhar', (string) ($validated['aadhar_number'] ?? '')],
            'pan' => ['pan', strtoupper((string) ($validated['pan_number'] ?? ''))],
        ];

        foreach ($documents as $key => [$documentType, $documentNumber]) {
            if (! $request->hasFile($key)) {
                continue;
            }

            $path = $request->file($key)->store("seller-verification/{$verification->id}", 'public');

            SellerVerificationDocument::query()->create([
                'seller_verification_id' => $verification->id,
                'document_type' => $documentType,
                'document_path' => $path,
                'document_number' => $documentNumber,
                'verification_status' => 'pending',
            ]);
        }

        $verification->load(['documents', 'category', 'subscriptionPackage']);

        return response()->json([
            'message' => 'Seller verification documents submitted successfully.',
            'data' => [
                'verification' => $this->serializeVerification($verification),
            ],
        ], 201);
    }

    private function serializeVerification(SellerVerification $verification): array
    {
        return [
            'id' => $verification->id,
            'status' => (string) $verification->status,
            'verified_at' => optional($verification->verified_at)?->toIso8601String(),
            'admin_notes' => $verification->admin_notes,
            'category' => [
                'id' => (int) $verification->category?->id,
                'name' => (string) ($verification->category?->name ?? ''),
                'slug' => (string) ($verification->category?->slug ?? ''),
            ],
            'package' => $verification->subscriptionPackage
                ? $this->serializePackage($verification->subscriptionPackage)
                : null,
            'documents' => $verification->documents
                ->map(fn (SellerVerificationDocument $document): array => [
                    'id' => $document->id,
                    'document_type' => (string) $document->document_type,
                    'document_number' => $document->document_number,
                    'verification_status' => (string) $document->verification_status,
                    'verification_note' => $document->verification_note,
                    'document_url' => $document->getDocumentUrl(),
                ])
                ->values()
                ->all(),
            'created_at' => optional($verification->created_at)?->toIso8601String(),
            'updated_at' => optional($verification->updated_at)?->toIso8601String(),
        ];
    }

    private function serializePackage(SubscriptionPackage $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'package_type' => $package->package_type,
            'package_type_label' => $package->package_type_label,
            'final_price' => (float) $package->final_price,
            'package_duration_label' => $package->package_duration_label,
            'item_limit_label' => $package->item_limit_label,
            'listing_duration_label' => $package->listing_duration_label,
            'ai_usage_limit_label' => $package->ai_usage_limit_label,
            'allows_call' => (bool) $package->allows_call,
            'allows_ai' => (bool) $package->allows_ai,
            'icon_url' => $package->icon_url,
            'category_name' => $package->category?->name,
            'category_scope' => $package->category_scope,
            'is_seller_verification' => (bool) $package->is_seller_verification,
            'seller_tier' => (string) ($package->seller_tier ?? ''),
            'seller_tier_label' => $package->seller_tier_label,
            'seller_badge_label' => $package->resolved_seller_badge_label,
            'required_documents' => array_values(array_filter((array) ($package->required_documents ?? []))),
            'key_points' => array_values(array_filter((array) ($package->key_points ?? []))),
        ];
    }

    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->listings()->exists()) {
            return response()->json([
                'message' => 'Analytics are available for seller accounts only.',
            ], 403);
        }

        $listings = $user->listings()->withCount(['favoritedBy'])->get();

        $totalViews = (int) $listings->sum('views');
        $totalFavorites = (int) $listings->sum('favorited_by_count');
        $totalConversations = $user->conversationsAsSeller()->count();
        $activeListings = $listings->where('status', 'active')->count();
        $pendingListings = $listings->where('status', 'pending')->count();
        $inactiveListings = $listings->whereIn('status', ['inactive', 'expired', 'rejected'])->count();

        $topListings = $listings
            ->sortByDesc('views')
            ->take(5)
            ->map(fn (Listing $listing): array => [
                'id' => $listing->id,
                'title' => (string) $listing->title,
                'views' => (int) $listing->views,
                'favorites' => (int) $listing->favorited_by_count,
                'status' => (string) $listing->status,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'total_listings' => $listings->count(),
                'active_listings' => $activeListings,
                'pending_listings' => $pendingListings,
                'inactive_listings' => $inactiveListings,
                'total_views' => $totalViews,
                'total_favorites' => $totalFavorites,
                'total_conversations' => $totalConversations,
                'top_listings' => $topListings,
            ],
        ]);
    }

    public function listVerifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $verifications = $user->sellerVerifications()
            ->with(['documents', 'category', 'subscriptionPackage'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data' => $verifications->items(),
            'verifications' => array_map(
                fn (SellerVerification $verification) => $this->serializeVerification($verification),
                $verifications->items()
            ),
            'pagination' => [
                'total' => $verifications->total(),
                'per_page' => $verifications->perPage(),
                'current_page' => $verifications->currentPage(),
                'last_page' => $verifications->lastPage(),
                'has_more' => $verifications->hasMorePages(),
            ],
        ]);
    }

    public function showVerification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $verification = SellerVerification::query()
            ->with(['documents', 'category', 'subscriptionPackage'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $this->serializeVerification($verification),
        ]);
    }

    public function destroyVerification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $verification = SellerVerification::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // Only allow deletion of pending or rejected verifications
        if (!in_array($verification->status, ['pending', 'rejected'])) {
            return response()->json([
                'message' => 'Can only cancel pending or rejected verifications.',
            ], 422);
        }

        $verification->delete();

        return response()->json([
            'message' => 'Verification cancelled successfully.',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'verification_status' => $user->seller_verification_status ?? 'unverified',
            'verification_date' => $user->seller_verified_at?->toIso8601String(),
            'seller_tier' => $user->seller_type ?? 'basic',
            'seller_badge' => $user->sellerBadgeLabel(),
        ];

        $listings = $user->listings();
        $activeListings = (clone $listings)->where('status', 'active')->count();
        $soldListings = (clone $listings)->where('status', 'sold')->count();
        $expiredListings = (clone $listings)->where('status', 'expired')->count();
        $draftListings = (clone $listings)->where('status', 'draft')->count();
        $totalListings = $listings->count();

        $recentListings = (clone $listings)
            ->with('category', 'images')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $unreadConversations = $user->conversationsAsSeller()
            ->wherePivot('last_seen_at', '<', now())
            ->count();

        $currentPackage = $user->primaryApprovedSellerVerification()?->subscriptionPackage;

        return response()->json([
            'data' => [
                'stats' => $stats,
                'listings' => [
                    'total' => $totalListings,
                    'active' => $activeListings,
                    'sold' => $soldListings,
                    'expired' => $expiredListings,
                    'draft' => $draftListings,
                ],
                'recent_listings' => $recentListings->map(fn (Listing $listing) => [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'status' => $listing->status,
                    'views' => $listing->views,
                    'image_url' => $listing->images->first()?->image_url,
                    'created_at' => $listing->created_at?->toIso8601String(),
                ]),
                'unread_messages' => $unreadConversations,
                'active_package' => $currentPackage ? $this->serializePackage($currentPackage) : null,
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'business_name' => $user->business_name,
                'business_description' => $user->business_description,
                'address' => $user->address,
                'city' => $user->city,
                'state' => $user->state,
                'pincode' => $user->pincode,
                'response_time' => $user->response_time,
                'business_hours' => $user->business_hours,
                'seller_type' => $user->seller_type,
                'seller_verified_at' => $user->seller_verified_at?->toIso8601String(),
                'is_seller_verified' => (bool) $user->is_seller_verified,
                'seller_badge_label' => $user->sellerBadgeLabel(),
            ],
        ]);
    }

    private function isSellerUser($user): bool
    {
        return $user->hasSellerVerificationAccess();
    }
}
