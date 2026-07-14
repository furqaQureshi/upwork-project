<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionPackageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_listing_package_with_specific_category(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Mobiles '.Str::upper(Str::random(4)),
            'slug' => 'mobiles-'.Str::lower(Str::random(8)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.subscription-packages.store'), [
            'name' => 'Starter Listing Pack',
            'package_type' => 'listing',
            'price' => '1000',
            'discount_percent' => '10',
            'package_duration_type' => 'limited',
            'package_duration_days' => '30',
            'item_limit_type' => 'limited',
            'item_limit_count' => '25',
            'listing_duration_type' => 'custom',
            'listing_duration_days' => '45',
            'category_scope' => 'specific',
            'category_id' => $category->id,
            'key_points' => "25 listings\nPriority support",
            'allows_call' => '1',
            'allows_ai' => '1',
            'ai_usage_limit_type' => 'limited',
            'ai_usage_limit_count' => '40',
            'icon_file' => UploadedFile::fake()->image('listing-pack.png'),
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.subscription-packages.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $package = SubscriptionPackage::query()->where('name', 'Starter Listing Pack')->first();

        $this->assertNotNull($package);
        $this->assertSame('listing', $package->package_type);
        $this->assertSame('specific', $package->category_scope);
        $this->assertSame($category->id, $package->category_id);
        $this->assertSame('900.00', number_format((float) $package->final_price, 2, '.', ''));
        $this->assertSame(['25 listings', 'Priority support'], $package->key_points);
        $this->assertTrue((bool) $package->allows_call);
        $this->assertTrue((bool) $package->allows_ai);
        $this->assertSame('limited', $package->ai_usage_limit_type);
        $this->assertSame(40, (int) $package->ai_usage_limit_count);
        $this->assertNotNull($package->icon);
        Storage::disk('public')->assertExists($package->icon);
    }

    public function test_featured_package_is_forced_global_category_scope(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Cars '.Str::upper(Str::random(4)),
            'slug' => 'cars-'.Str::lower(Str::random(8)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.subscription-packages.store'), [
            'name' => 'Featured Boost Pro',
            'package_type' => 'featured',
            'price' => '500',
            'discount_percent' => '5',
            'package_duration_type' => 'unlimited',
            'item_limit_type' => 'unlimited',
            'listing_duration_type' => 'standard',
            'category_scope' => 'specific',
            'category_id' => $category->id,
            'key_points' => 'Top placement,Better visibility',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.subscription-packages.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $package = SubscriptionPackage::query()->where('name', 'Featured Boost Pro')->first();

        $this->assertNotNull($package);
        $this->assertSame('featured', $package->package_type);
        $this->assertSame('global', $package->category_scope);
        $this->assertNull($package->category_id);
        $this->assertSame('475.00', number_format((float) $package->final_price, 2, '.', ''));
        $this->assertFalse((bool) $package->allows_ai);
        $this->assertSame('limited', $package->ai_usage_limit_type);
        $this->assertNull($package->ai_usage_limit_count);
    }

    public function test_admin_can_view_index_show_and_edit_pages(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $package = SubscriptionPackage::query()->create([
            'name' => 'Visibility Plus',
            'package_type' => 'featured',
            'price' => 999,
            'discount_percent' => 0,
            'final_price' => 999,
            'package_duration_type' => 'limited',
            'package_duration_days' => 30,
            'item_limit_type' => 'limited',
            'item_limit_count' => 5,
            'listing_duration_type' => 'standard',
            'listing_duration_days' => 30,
            'category_scope' => 'global',
            'key_points' => ['Priority listing'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscription-packages.index'))
            ->assertOk()
            ->assertSee('Subscription Management');

        $this->actingAs($admin)
            ->get(route('admin.subscription-packages.show', $package))
            ->assertOk()
            ->assertSee('Visibility Plus');

        $this->actingAs($admin)
            ->get(route('admin.subscription-packages.edit', $package))
            ->assertOk()
            ->assertSee('Edit Visibility Plus');
    }
}
