<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'state',
        'avatar',
        'verification_document_type',
        'verification_document_number',
        'verification_document_path',
        'seller_verification_status',
        'seller_verified_at',
        'seller_type',
        'seller_active_package_id',
        'seller_verification_note',
        'is_admin',
        'is_blocked',
        'last_seen_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'seller_verified_at' => 'datetime',
            'seller_active_package_id' => 'integer',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function activeSellerPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'seller_active_package_id');
    }

    public function getIsSellerVerifiedAttribute(): bool
    {
        return $this->seller_verification_status === 'approved' && $this->seller_verified_at !== null;
    }

    public function getVerificationDocumentUrlAttribute(): ?string
    {
        if (! $this->verification_document_path) {
            return null;
        }

        $path = (string) $this->verification_document_path;

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function favoriteListings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites')->withTimestamps();
    }

    public function conversationsAsBuyer(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function conversationsAsSeller(): HasMany
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function listingReports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    public function featuredAdPayments(): HasMany
    {
        return $this->hasMany(FeaturedAdPayment::class);
    }

    public function subscriptionPackagePurchases(): HasMany
    {
        return $this->hasMany(SubscriptionPackagePurchase::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function featureAccesses(): HasMany
    {
        return $this->hasMany(UserFeatureAccess::class);
    }

    public function sellerVerifications(): HasMany
    {
        return $this->hasMany(SellerVerification::class);
    }

    public function approvedSellerVerifications(): HasMany
    {
        return $this->hasMany(SellerVerification::class)
            ->where('status', 'approved');
    }

    public function hasSellerVerificationAccess(): bool
    {
        return $this->sellerVerifications()->exists()
            || $this->listings()->exists()
            || trim((string) $this->seller_verification_status) !== ''
            || trim((string) $this->seller_type) !== '';
    }

    public function isPremiumSellerVerified(): bool
    {
        return $this->sellerVerifications()
            ->where('status', 'approved')
            ->whereHas('subscriptionPackage', function ($query): void {
                $query->where('seller_tier', 'premium_verified');
            })
            ->exists();
    }

    public function primaryApprovedSellerVerification(): ?SellerVerification
    {
        $priority = [
            'premium_verified' => 3,
            'car_verified' => 2,
            'verified' => 1,
        ];

        return $this->sellerVerifications()
            ->with(['subscriptionPackage', 'category.parent'])
            ->where('status', 'approved')
            ->get()
            ->sortByDesc(function (SellerVerification $verification) use ($priority): int {
                $tier = (string) ($verification->subscriptionPackage?->seller_tier ?? '');
                $weight = $priority[$tier] ?? 0;
                $timestamp = $verification->verified_at?->timestamp ?? $verification->created_at?->timestamp ?? 0;

                return ($weight * 1000000000) + $timestamp;
            })
            ->first();
    }

    public function applicableSellerVerificationForCategory(?Category $category = null): ?SellerVerification
    {
        $priority = [
            'premium_verified' => 3,
            'car_verified' => 2,
            'verified' => 1,
        ];

        return $this->sellerVerifications()
            ->with(['subscriptionPackage', 'category.parent'])
            ->where('status', 'approved')
            ->get()
            ->filter(function (SellerVerification $verification) use ($category): bool {
                $package = $verification->subscriptionPackage;

                if (! $package || ! $package->is_seller_verification) {
                    return false;
                }

                if (! $category) {
                    return true;
                }

                return $package->appliesToCategory($category);
            })
            ->sortByDesc(function (SellerVerification $verification) use ($priority): int {
                $tier = (string) ($verification->subscriptionPackage?->seller_tier ?? '');
                $weight = $priority[$tier] ?? 0;
                $timestamp = $verification->verified_at?->timestamp ?? $verification->created_at?->timestamp ?? 0;

                return ($weight * 1000000000) + $timestamp;
            })
            ->first();
    }

    public function sellerBadgeLabel(): string
    {
        $package = $this->primaryApprovedSellerVerification()?->subscriptionPackage;

        return $package?->resolved_seller_badge_label ?? '';
    }

    public function syncSellerStateFromApprovedVerifications(): void
    {
        $primary = $this->primaryApprovedSellerVerification();

        if ($primary && $primary->subscriptionPackage) {
            $this->forceFill([
                'seller_verification_status' => 'approved',
                'seller_verified_at' => $primary->verified_at ?? now(),
                'seller_type' => $primary->subscriptionPackage->seller_tier,
                'seller_active_package_id' => $primary->subscription_package_id,
                'seller_verification_note' => $primary->admin_notes,
            ])->saveQuietly();

            return;
        }

        $latest = $this->sellerVerifications()->latest('id')->first();

        $this->forceFill([
            'seller_verification_status' => $latest?->status,
            'seller_verified_at' => null,
            'seller_type' => null,
            'seller_active_package_id' => null,
            'seller_verification_note' => $latest?->admin_notes,
        ])->saveQuietly();
    }

    public function isCarSellerVerified(): bool
    {
        return $this->sellerVerifications()
            ->where('status', 'approved')
            ->whereHas('subscriptionPackage', function ($query): void {
                $query->where('seller_tier', 'car_verified');
            })
            ->exists();
    }

    public function getCarSellerVerification(): ?SellerVerification
    {
        return $this->sellerVerifications()
            ->where('status', 'approved')
            ->whereHas('subscriptionPackage', function ($query): void {
                $query->where('seller_tier', 'car_verified');
            })
            ->latest()
            ->first();
    }
}
