<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerVerificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_listing_card_shows_verified_badge_only_for_document_approved_seller(): void
    {
        [$seller, $listing] = $this->createApprovedListingForSeller();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Verified seller');

        $seller->update([
            'seller_verification_status' => 'approved',
            'seller_verified_at' => now(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($listing->title)
            ->assertSee('Verified seller');
    }

    public function test_listing_detail_shows_verified_badge_only_for_document_approved_seller(): void
    {
        [$seller, $listing] = $this->createApprovedListingForSeller();

        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertDontSee('Verified seller');

        $seller->update([
            'seller_verification_status' => 'approved',
            'seller_verified_at' => now(),
        ]);

        $this->get(route('listings.show', $listing->refresh()))
            ->assertOk()
            ->assertSee($listing->title)
            ->assertSee('Verified seller');
    }

    /**
     * @return array{0: User, 1: Listing}
     */
    private function createApprovedListingForSeller(): array
    {
        $seller = User::factory()->create([
            'email_verified_at' => now(),
            'seller_verification_status' => 'pending',
            'seller_verified_at' => null,
        ]);

        $category = Category::query()->create([
            'name' => 'Verification Category '.Str::upper(Str::random(4)),
            'slug' => 'verification-category-'.Str::lower(Str::random(8)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $listing = Listing::query()->create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Verification Test Listing '.Str::upper(Str::random(4)),
            'slug' => 'verification-test-listing-'.Str::lower(Str::random(8)),
            'description' => 'Listing description long enough to satisfy validation rules and rendering expectations.',
            'price' => 75000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'address' => 'Koramangala',
            'status' => 'approved',
            'is_featured' => false,
            'published_at' => now()->subHour(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$seller, $listing];
    }
}
