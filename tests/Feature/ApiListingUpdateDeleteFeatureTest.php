<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiListingUpdateDeleteFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_listing_and_replace_images(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Cars',
            'slug' => 'cars',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $listing = Listing::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Old title',
            'slug' => 'old-title',
            'description' => 'Old description with enough content for validation checks.',
            'price_type' => 'fixed',
            'price' => 100000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $oldPath = 'listings/'.$listing->id.'/old-image.jpg';
        Storage::disk('public')->put($oldPath, 'old-image-content');

        ListingImage::query()->create([
            'listing_id' => $listing->id,
            'path' => $oldPath,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->patch('/api/v1/listings/'.$listing->id, [
                'title' => 'Updated title',
                'category_id' => $category->id,
                'price_type' => 'fixed',
                'price' => 120000,
                'description' => 'Updated description that also satisfies the required minimum length.',
                'condition' => 'used',
                'city' => 'Patna',
                'state' => 'Bihar',
                'images' => [
                    UploadedFile::fake()->image('new-image.jpg'),
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');

        $listing->refresh();
        $this->assertSame('Updated title', $listing->title);

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertCount(1, $listing->images()->get());
        $this->assertNotSame($oldPath, $listing->images()->first()?->path);
        Storage::disk('public')->assertExists((string) $listing->images()->first()?->path);
    }

    public function test_non_owner_cannot_update_listing(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Mobiles',
            'slug' => 'mobiles',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $listing = Listing::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Owner listing',
            'slug' => 'owner-listing',
            'description' => 'Owner listing description with enough content for all validation requirements.',
            'price_type' => 'fixed',
            'price' => 5000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($otherUser, 'sanctum')
            ->patchJson('/api/v1/listings/'.$listing->id, [
                'title' => 'Hacked title',
                'category_id' => $category->id,
                'description' => 'Hacked description with sufficient length for validation purposes.',
                'price_type' => 'fixed',
                'price' => 6000,
                'condition' => 'used',
                'city' => 'Patna',
            ]);

        $response->assertForbidden();
        $this->assertSame('Owner listing', $listing->refresh()->title);
    }

    public function test_non_owner_cannot_delete_listing(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Bikes',
            'slug' => 'bikes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $listing = Listing::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Owner bike',
            'slug' => 'owner-bike',
            'description' => 'Bike description text with enough content for listing payload requirements.',
            'price_type' => 'fixed',
            'price' => 45000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($otherUser, 'sanctum')
            ->deleteJson('/api/v1/listings/'.$listing->id);

        $response->assertForbidden();
        $this->assertDatabaseHas('listings', ['id' => $listing->id]);
    }

    public function test_owner_can_delete_listing(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Furniture',
            'slug' => 'furniture',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $listing = Listing::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Sofa set',
            'slug' => 'sofa-set',
            'description' => 'Sofa description text with enough details to satisfy backend validations and rules.',
            'price_type' => 'fixed',
            'price' => 15000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $path = 'listings/'.$listing->id.'/sofa.jpg';
        Storage::disk('public')->put($path, 'sofa-image-content');

        ListingImage::query()->create([
            'listing_id' => $listing->id,
            'path' => $path,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->deleteJson('/api/v1/listings/'.$listing->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Listing deleted successfully.');

        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
