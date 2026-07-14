<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FreePostLimit;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FreePostLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_category_rule_applies_across_all_child_categories(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $parent = $this->createCategory('Vehicles');
        $childOne = $this->createCategory('Cars', $parent->id);
        $childTwo = $this->createCategory('Bikes', $parent->id);

        FreePostLimit::create([
            'category_id' => $parent->id,
            'window_days' => 30,
            'limit_count' => 1,
        ]);

        $this->createExistingListing($user, $childOne->id, 'Existing Car Listing');

        $response = $this->actingAs($user)->post(route('listings.store'), $this->listingPayload($childTwo->id, 'Bike Listing Attempt'));

        $response->assertSessionHasErrors('category_id');
        $error = (string) session('errors')->first('category_id');

        $this->assertStringContainsString('1 ad(s)', $error);
        $this->assertStringContainsString('30 days', $error);
        $this->assertStringContainsString('Vehicles', $error);

        $this->assertDatabaseMissing('listings', [
            'title' => 'Bike Listing Attempt',
        ]);
    }

    public function test_child_category_rule_takes_priority_over_parent_rule(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $parent = $this->createCategory('Electronics');
        $childOne = $this->createCategory('Mobiles', $parent->id);
        $childTwo = $this->createCategory('Laptops', $parent->id);

        FreePostLimit::create([
            'category_id' => $parent->id,
            'window_days' => 30,
            'limit_count' => 5,
        ]);

        FreePostLimit::create([
            'category_id' => $childOne->id,
            'window_days' => 30,
            'limit_count' => 1,
        ]);

        $this->createExistingListing($user, $childOne->id, 'Existing Mobile Listing');

        $blocked = $this->actingAs($user)->post(route('listings.store'), $this->listingPayload($childOne->id, 'Second Mobile Listing'));

        $blocked->assertSessionHasErrors('category_id');
        $blockedError = (string) session('errors')->first('category_id');

        $this->assertStringContainsString('1 ad(s)', $blockedError);
        $this->assertStringContainsString('Mobiles', $blockedError);

        $allowed = $this->actingAs($user)->post(route('listings.store'), $this->listingPayload($childTwo->id, 'Laptop Listing Allowed'));

        $allowed->assertSessionHasNoErrors();

        $this->assertDatabaseHas('listings', [
            'title' => 'Laptop Listing Allowed',
            'category_id' => $childTwo->id,
            'user_id' => $user->id,
        ]);
    }

    private function createCategory(string $name, ?int $parentId = null): Category
    {
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return Category::create([
            'parent_id' => $parentId,
            'name' => $name.' '.Str::upper(Str::random(3)),
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => 0,
            'condition_enabled' => true,
        ]);
    }

    private function createExistingListing(User $user, int $categoryId, string $title): Listing
    {
        return Listing::create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => 'This is a pre-existing listing used to evaluate free posting limits.',
            'price' => 1000,
            'price_type' => 'fixed',
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Mumbai',
            'state' => 'MH',
            'status' => 'approved',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
    }

    private function listingPayload(int $categoryId, string $title): array
    {
        return [
            'title' => $title,
            'category_id' => $categoryId,
            'price_type' => 'fixed',
            'price' => 1500,
            'description' => 'Great condition item with proper details for posting validation.',
            'condition' => 'used',
            'city' => 'Mumbai',
            'state' => 'MH',
            'images' => [
                UploadedFile::fake()->image(Str::slug($title).'.jpg'),
            ],
        ];
    }
}
