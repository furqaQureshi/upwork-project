<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Notifications\ListingDeletedByAdminNotification;
use App\Services\WebPush\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminListingModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_listing_individually_and_notify_seller(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$seller, $listing] = $this->createSellerListing('Single Delete Listing');

        $imagePath = 'listing-images/'.$listing->id.'/single-delete.jpg';

        Storage::disk('public')->put($imagePath, 'image-bytes');

        ListingImage::query()->create([
            'listing_id' => $listing->id,
            'path' => $imagePath,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->mock(WebPushService::class, function (MockInterface $mock) use ($seller): void {
            $mock->shouldReceive('sendToUser')
                ->once()
                ->withArgs(function (User $user, array $payload) use ($seller): bool {
                    return $user->is($seller)
                        && ($payload['title'] ?? null) === 'Listing removed by admin'
                        && (($payload['data']['listing_status'] ?? null) === 'deleted');
                });
        });

        $this->actingAs($admin)
            ->delete(route('admin.listings.destroy', $listing), [
                'reason' => 'Policy violation on prohibited content.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Listing deleted successfully.');

        $this->assertDatabaseMissing('listings', [
            'id' => $listing->id,
        ]);
        Storage::disk('public')->assertMissing($imagePath);

        Notification::assertSentTo($seller, ListingDeletedByAdminNotification::class);
    }

    public function test_admin_can_bulk_delete_listings_and_notify_owners(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        [$sellerOne, $listingOne] = $this->createSellerListing('Bulk Delete Seller One');
        [$sellerTwo, $listingTwo] = $this->createSellerListing('Bulk Delete Seller Two');

        $this->mock(WebPushService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToUser')->twice();
        });

        $this->actingAs($admin)
            ->post(route('admin.listings.bulk-destroy'), [
                'listing_ids' => [$listingOne->id, $listingTwo->id],
                'reason' => 'Bulk cleanup for policy violations.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '2 listing(s) deleted successfully.');

        $this->assertDatabaseMissing('listings', [
            'id' => $listingOne->id,
        ]);
        $this->assertDatabaseMissing('listings', [
            'id' => $listingTwo->id,
        ]);

        Notification::assertSentTo($sellerOne, ListingDeletedByAdminNotification::class);
        Notification::assertSentTo($sellerTwo, ListingDeletedByAdminNotification::class);
    }

    public function test_non_admin_cannot_delete_listings_from_admin_routes(): void
    {
        [$seller, $listing] = $this->createSellerListing('Unauthorized Delete Listing');

        $this->actingAs($seller)
            ->delete(route('admin.listings.destroy', $listing))
            ->assertForbidden();

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Listing}
     */
    private function createSellerListing(string $title): array
    {
        $seller = User::factory()->create([
            'is_admin' => false,
        ]);

        $category = Category::query()->create([
            'name' => 'Listing Moderation Category '.Str::upper(Str::random(4)),
            'slug' => 'listing-moderation-category-'.Str::lower(Str::random(7)),
            'is_active' => true,
        ]);

        $listing = Listing::query()->create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => $title.' '.Str::upper(Str::random(4)),
            'slug' => Str::slug($title.'-'.Str::random(8)),
            'description' => 'Listing description for admin moderation delete tests.',
            'price' => 20000,
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
