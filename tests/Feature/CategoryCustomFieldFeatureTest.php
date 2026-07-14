<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomField;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryCustomFieldFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_subcategory_with_icon_upload(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $parent = Category::query()->create([
            'name' => 'Vehicles '.Str::upper(Str::random(4)),
            'slug' => 'vehicles-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Cars '.Str::upper(Str::random(5)),
            'parent_id' => $parent->id,
            'icon_file' => UploadedFile::fake()->image('cars.png'),
            'sort_order' => 2,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.categories.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $subcategory = Category::query()->where('parent_id', $parent->id)->latest('id')->first();

        $this->assertNotNull($subcategory);
        $this->assertNotNull($subcategory->icon);
        Storage::disk('public')->assertExists($subcategory->icon);
    }

    public function test_admin_can_create_custom_field_for_subcategory(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $parent = Category::query()->create([
            'name' => 'Mobiles '.Str::upper(Str::random(4)),
            'slug' => 'mobiles-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subcategory = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Smartphones '.Str::upper(Str::random(4)),
            'slug' => 'smartphones-'.Str::lower(Str::random(6)),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.custom-fields.store'), [
            'category_id' => $subcategory->id,
            'name' => 'Color',
            'field_type' => 'dropdown',
            'options' => "Red\nBlue",
            'min_length' => 1,
            'max_length' => 20,
            'icon_file' => UploadedFile::fake()->image('color.png'),
            'sort_order' => 1,
            'is_required' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.custom-fields.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $field = CustomField::query()->where('category_id', $subcategory->id)->where('name', 'Color')->first();

        $this->assertNotNull($field);
        $this->assertSame('dropdown', $field->field_type);
        $this->assertSame(['Red', 'Blue'], $field->options);
        $this->assertTrue($field->is_required);
        $this->assertNotNull($field->icon);
        Storage::disk('public')->assertExists($field->icon);
    }

    public function test_user_can_create_listing_with_parent_and_subcategory_custom_fields(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $parent = Category::query()->create([
            'name' => 'Electronics '.Str::upper(Str::random(4)),
            'slug' => 'electronics-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subcategory = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Laptops '.Str::upper(Str::random(4)),
            'slug' => 'laptops-'.Str::lower(Str::random(6)),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $parentField = CustomField::query()->create([
            'category_id' => $parent->id,
            'name' => 'Brand',
            'slug' => 'brand',
            'field_type' => 'text',
            'min_length' => 2,
            'max_length' => 30,
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);

        $subcategoryField = CustomField::query()->create([
            'category_id' => $subcategory->id,
            'name' => 'Condition Grade',
            'slug' => 'condition-grade',
            'field_type' => 'dropdown',
            'options' => ['A', 'B', 'C'],
            'sort_order' => 2,
            'is_required' => true,
            'is_active' => true,
        ]);

        $fileField = CustomField::query()->create([
            'category_id' => $subcategory->id,
            'name' => 'Warranty Card',
            'slug' => 'warranty-card',
            'field_type' => 'file',
            'sort_order' => 3,
            'is_required' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'MacBook Pro 14 inch M2',
            'category_id' => $subcategory->id,
            'price' => '149999',
            'description' => 'Excellent laptop with complete accessories and valid bill. Very well maintained unit.',
            'condition' => 'used',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'address' => 'Whitefield',
            'images' => [
                UploadedFile::fake()->image('laptop-1.jpg'),
            ],
            'custom_fields' => [
                $parentField->id => 'Apple',
                $subcategoryField->id => 'A',
                $fileField->id => UploadedFile::fake()->image('warranty.jpg'),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $listing = Listing::query()->latest('id')->first();

        $this->assertNotNull($listing);
        $this->assertSame($subcategory->id, $listing->category_id);

        $brandValue = $listing->customFieldValues()->where('custom_field_id', $parentField->id)->first();
        $gradeValue = $listing->customFieldValues()->where('custom_field_id', $subcategoryField->id)->first();
        $fileValue = $listing->customFieldValues()->where('custom_field_id', $fileField->id)->first();

        $this->assertSame('Apple', $brandValue?->value_text);
        $this->assertSame('A', $gradeValue?->value_text);
        $this->assertNotNull($fileValue?->value_text);
        Storage::disk('public')->assertExists((string) $fileValue?->value_text);
    }

    public function test_user_can_create_listing_when_condition_is_disabled_for_category(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $category = Category::query()->create([
            'name' => 'Cars '.Str::upper(Str::random(4)),
            'slug' => 'cars-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
            'condition_enabled' => false,
        ]);

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'Selling my i20 in good running condition',
            'category_id' => $category->id,
            'price_type' => 'fixed',
            'price' => '450000',
            'description' => 'Single owner car with valid insurance, clean papers, and smooth engine performance.',
            'city' => 'Patna',
            'state' => 'Bihar',
            'address' => 'Dhanaut, Garden Road',
            'images' => [
                UploadedFile::fake()->image('car-1.jpg'),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $listing = Listing::query()->latest('id')->first();

        $this->assertNotNull($listing);
        $this->assertSame($category->id, $listing->category_id);
        $this->assertSame('used', $listing->condition);
    }

    public function test_home_page_filters_listings_by_text_custom_field_value(): void
    {
        $seller = User::factory()->create();

        $category = Category::query()->create([
            'name' => 'Phones '.Str::upper(Str::random(4)),
            'slug' => 'phones-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $brandField = CustomField::query()->create([
            'category_id' => $category->id,
            'name' => 'Brand',
            'slug' => 'brand',
            'field_type' => 'text',
            'sort_order' => 1,
            'is_required' => false,
            'is_active' => true,
        ]);

        $appleListing = $this->createApprovedListing($seller, $category, 'iPhone 14 Pro Max', 'iphone-14-pro-max-'.Str::lower(Str::random(5)));
        $samsungListing = $this->createApprovedListing($seller, $category, 'Samsung Galaxy S23', 'samsung-galaxy-s23-'.Str::lower(Str::random(5)));

        $appleListing->customFieldValues()->create([
            'custom_field_id' => $brandField->id,
            'value_text' => 'Apple',
        ]);

        $samsungListing->customFieldValues()->create([
            'custom_field_id' => $brandField->id,
            'value_text' => 'Samsung',
        ]);

        $response = $this->get(route('home', [
            'category' => $category->id,
            'custom_filters' => [
                $brandField->id => 'Apple',
            ],
        ]));

        $response->assertOk();
        $response->assertSee('iPhone 14 Pro Max');
        $response->assertDontSee('Samsung Galaxy S23');
    }

    public function test_home_page_filters_subcategory_listings_by_parent_custom_dropdown_field(): void
    {
        $seller = User::factory()->create();

        $parent = Category::query()->create([
            'name' => 'Vehicles '.Str::upper(Str::random(4)),
            'slug' => 'vehicles-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subcategory = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Cars '.Str::upper(Str::random(4)),
            'slug' => 'cars-'.Str::lower(Str::random(6)),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $colorField = CustomField::query()->create([
            'category_id' => $parent->id,
            'name' => 'Color',
            'slug' => 'color',
            'field_type' => 'dropdown',
            'options' => ['Red', 'Blue', 'White'],
            'sort_order' => 1,
            'is_required' => false,
            'is_active' => true,
        ]);

        $redListing = $this->createApprovedListing($seller, $subcategory, 'Honda City Red', 'honda-city-red-'.Str::lower(Str::random(5)));
        $blueListing = $this->createApprovedListing($seller, $subcategory, 'Honda City Blue', 'honda-city-blue-'.Str::lower(Str::random(5)));

        $redListing->customFieldValues()->create([
            'custom_field_id' => $colorField->id,
            'value_text' => 'Red',
        ]);

        $blueListing->customFieldValues()->create([
            'custom_field_id' => $colorField->id,
            'value_text' => 'Blue',
        ]);

        $response = $this->get(route('home', [
            'category' => $subcategory->id,
            'custom_filters' => [
                $colorField->id => 'Red',
            ],
        ]));

        $response->assertOk();
        $response->assertSee('Honda City Red');
        $response->assertDontSee('Honda City Blue');
    }

    public function test_category_browse_page_shows_smart_filters_for_selected_category(): void
    {
        $seller = User::factory()->create();

        $parent = Category::query()->create([
            'name' => 'Electronics '.Str::upper(Str::random(4)),
            'slug' => 'electronics-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subcategory = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Laptops '.Str::upper(Str::random(4)),
            'slug' => 'laptops-'.Str::lower(Str::random(6)),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $brandField = CustomField::query()->create([
            'category_id' => $parent->id,
            'name' => 'Brand',
            'slug' => 'brand',
            'field_type' => 'dropdown',
            'options' => ['Apple', 'Dell'],
            'sort_order' => 1,
            'is_required' => false,
            'is_active' => true,
        ]);

        $listing = $this->createApprovedListing($seller, $subcategory, 'MacBook Air M2', 'macbook-air-m2-'.Str::lower(Str::random(5)));

        $listing->customFieldValues()->create([
            'custom_field_id' => $brandField->id,
            'value_text' => 'Apple',
        ]);

        $response = $this->get(route('categories.show', [
            'category' => $parent->slug,
            'subcategory' => $subcategory->id,
        ]));

        $response->assertOk();
        $response->assertSee('Smart Filters');
        $response->assertSee('Brand');
        $response->assertSee('MacBook Air M2');
    }

    public function test_category_browse_page_filters_listings_by_custom_field_value(): void
    {
        $seller = User::factory()->create();

        $parent = Category::query()->create([
            'name' => 'Vehicles '.Str::upper(Str::random(4)),
            'slug' => 'vehicles-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subcategory = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Cars '.Str::upper(Str::random(4)),
            'slug' => 'cars-'.Str::lower(Str::random(6)),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $colorField = CustomField::query()->create([
            'category_id' => $parent->id,
            'name' => 'Color',
            'slug' => 'color',
            'field_type' => 'dropdown',
            'options' => ['Red', 'Blue', 'White'],
            'sort_order' => 1,
            'is_required' => false,
            'is_active' => true,
        ]);

        $redListing = $this->createApprovedListing($seller, $subcategory, 'Honda City Red Browse', 'honda-city-red-browse-'.Str::lower(Str::random(5)));
        $blueListing = $this->createApprovedListing($seller, $subcategory, 'Honda City Blue Browse', 'honda-city-blue-browse-'.Str::lower(Str::random(5)));

        $redListing->customFieldValues()->create([
            'custom_field_id' => $colorField->id,
            'value_text' => 'Red',
        ]);

        $blueListing->customFieldValues()->create([
            'custom_field_id' => $colorField->id,
            'value_text' => 'Blue',
        ]);

        $response = $this->get(route('categories.show', [
            'category' => $parent->slug,
            'subcategory' => $subcategory->id,
            'custom_filters' => [
                $colorField->id => 'Red',
            ],
        ]));

        $response->assertOk();
        $response->assertSee('Honda City Red Browse');
        $response->assertDontSee('Honda City Blue Browse');
    }

    private function createApprovedListing(User $seller, Category $category, string $title, string $slug): Listing
    {
        return Listing::query()->create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Clean test listing description with enough text for validation in tests.',
            'price' => 50000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'address' => 'Indiranagar',
            'status' => 'approved',
            'is_featured' => false,
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
