<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSellerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_seller_index_and_profile_pages(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$seller, $listing] = $this->createSellerWithListing('Alpha Seller');

        $nonSeller = User::factory()->create([
            'name' => 'No Listing User',
            'is_admin' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sellers.index'))
            ->assertOk()
            ->assertSee('Seller Management')
            ->assertSee($seller->email)
            ->assertDontSee($nonSeller->email);

        $this->actingAs($admin)
            ->get(route('admin.sellers.show', $seller))
            ->assertOk()
            ->assertSee($seller->email)
            ->assertSee($listing->title);
    }

    public function test_non_admin_cannot_access_seller_management_routes(): void
    {
        [$seller] = $this->createSellerWithListing('Normal Seller');

        $this->actingAs($seller)
            ->get(route('admin.sellers.index'))
            ->assertForbidden();
    }

    public function test_admin_can_toggle_seller_block_status(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$seller] = $this->createSellerWithListing('Blockable Seller');

        $this->assertFalse((bool) $seller->is_blocked);

        $this->actingAs($admin)
            ->post(route('admin.sellers.toggle-block', $seller))
            ->assertRedirect()
            ->assertSessionHas('status', 'Seller status updated successfully.');

        $this->assertTrue((bool) $seller->fresh()->is_blocked);

        $this->actingAs($admin)
            ->post(route('admin.sellers.toggle-block', $seller))
            ->assertRedirect();

        $this->assertFalse((bool) $seller->fresh()->is_blocked);
    }

    public function test_admin_can_filter_blocked_sellers(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$activeSeller] = $this->createSellerWithListing('Active Seller');
        [$blockedSeller] = $this->createSellerWithListing('Blocked Seller');

        $blockedSeller->update([
            'is_blocked' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sellers.index', [
                'status' => 'blocked',
            ]))
            ->assertOk()
            ->assertSee($blockedSeller->email)
            ->assertDontSee($activeSeller->email);
    }

    public function test_admin_seller_routes_return_not_found_for_admin_accounts(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $adminSeller = User::factory()->create([
            'name' => 'Admin With Listing',
            'is_admin' => true,
        ]);

        $category = Category::create([
            'name' => 'Admin Seller Category '.Str::upper(Str::random(4)),
            'slug' => 'admin-seller-category-'.Str::lower(Str::random(7)),
            'is_active' => true,
        ]);

        Listing::create([
            'user_id' => $adminSeller->id,
            'category_id' => $category->id,
            'title' => 'Admin listing '.Str::upper(Str::random(4)),
            'slug' => Str::slug('admin-listing-'.Str::random(8)),
            'description' => 'This listing belongs to an admin account.',
            'price' => 10000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sellers.show', $adminSeller))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.sellers.toggle-block', $adminSeller))
            ->assertNotFound();
    }

    public function test_admin_can_trigger_seller_test_push_check_without_subscriptions(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$seller] = $this->createSellerWithListing('Pushless Seller');

        $this->actingAs($admin)
            ->post(route('admin.sellers.test-push', $seller))
            ->assertRedirect()
            ->assertSessionHas('status', "No active push subscriptions found for {$seller->name}.");
    }

    public function test_admin_can_approve_seller_document_verification(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$seller] = $this->createSellerWithListing('Pending KYC Seller');

        $seller->update([
            'verification_document_type' => 'Aadhaar Card',
            'verification_document_number' => 'XXXX-XXXX-1234',
            'verification_document_path' => 'seller-documents/'.$seller->id.'/aadhaar.pdf',
            'seller_verification_status' => 'pending',
            'seller_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.verification.approve', $seller))
            ->assertRedirect()
            ->assertSessionHas('status', 'Seller document verification approved successfully.');

        $seller->refresh();

        $this->assertSame('approved', $seller->seller_verification_status);
        $this->assertNotNull($seller->seller_verified_at);
        $this->assertNull($seller->seller_verification_note);
    }

    public function test_admin_can_reject_seller_document_verification_with_reason(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$seller] = $this->createSellerWithListing('Rejectable KYC Seller');

        $seller->update([
            'verification_document_type' => 'PAN Card',
            'verification_document_number' => 'ABCDE1234F',
            'verification_document_path' => 'seller-documents/'.$seller->id.'/pan.pdf',
            'seller_verification_status' => 'pending',
        ]);

        $reason = 'Document number is unclear. Please upload a clearer scan.';

        $this->actingAs($admin)
            ->post(route('admin.sellers.verification.reject', $seller), [
                'reason' => $reason,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Seller document verification rejected with feedback.');

        $seller->refresh();

        $this->assertSame('rejected', $seller->seller_verification_status);
        $this->assertNull($seller->seller_verified_at);
        $this->assertSame($reason, $seller->seller_verification_note);
    }

    /**
     * @return array{0: User, 1: Listing}
     */
    private function createSellerWithListing(string $sellerName): array
    {
        $seller = User::factory()->create([
            'name' => $sellerName,
            'is_admin' => false,
            'is_blocked' => false,
        ]);

        $category = Category::create([
            'name' => 'Seller Category '.Str::upper(Str::random(4)),
            'slug' => 'seller-category-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Seller Listing '.Str::upper(Str::random(4)),
            'slug' => Str::slug('seller-listing-'.Str::random(8)),
            'description' => 'Listing created for seller management tests.',
            'price' => 25000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'pending',
            'published_at' => now(),
        ]);

        return [$seller, $listing];
    }
}
