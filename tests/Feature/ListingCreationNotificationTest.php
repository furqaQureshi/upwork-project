<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\User;
use App\Notifications\ListingCreatedNotification;
use App\Services\WebPush\WebPushService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ListingCreationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_creation_sends_pending_notification_when_moderation_is_enabled(): void
    {
        Storage::fake('public');
        Notification::fake();
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('listing_moderation_enabled', '1');

        $user = User::factory()->create();
        $category = $this->createCategory('Phones');

        $this->mock(WebPushService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('sendToUser')
                ->once()
                ->withArgs(function (User $recipient, array $payload) use ($user): bool {
                    return $recipient->is($user)
                        && ($payload['title'] ?? null) === 'Listing submitted'
                        && (($payload['data']['listing_status'] ?? null) === 'pending');
                });
        });

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'Pending Notification Listing',
            'category_id' => $category->id,
            'price' => '32000',
            'description' => 'A clean listing description with enough text for validation and moderation checks.',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'address' => 'Kankarbagh',
            'images' => [UploadedFile::fake()->image('listing.jpg')],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ListingCreatedNotification::class, function (ListingCreatedNotification $notification, array $channels) use ($user): bool {
            $data = $notification->toArray($user);

            return in_array('database', $channels, true)
                && ($data['title'] ?? null) === 'Listing submitted'
                && ($data['listing_status'] ?? null) === 'pending';
        });
    }

    public function test_listing_creation_sends_published_notification_when_moderation_is_disabled(): void
    {
        Storage::fake('public');
        Notification::fake();
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('listing_moderation_enabled', '0');

        $user = User::factory()->create();
        $category = $this->createCategory('Furniture');

        $this->mock(WebPushService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('sendToUser')
                ->once()
                ->withArgs(function (User $recipient, array $payload) use ($user): bool {
                    return $recipient->is($user)
                        && ($payload['title'] ?? null) === 'Listing published'
                        && (($payload['data']['listing_status'] ?? null) === 'approved');
                });
        });

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'Published Notification Listing',
            'category_id' => $category->id,
            'price' => '18000',
            'description' => 'Solid product description with enough text so that validation passes cleanly.',
            'condition' => 'used',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'address' => 'Hinjewadi',
            'images' => [UploadedFile::fake()->image('listing.jpg')],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ListingCreatedNotification::class, function (ListingCreatedNotification $notification, array $channels) use ($user): bool {
            $data = $notification->toArray($user);

            return in_array('database', $channels, true)
                && ($data['title'] ?? null) === 'Listing published'
                && ($data['listing_status'] ?? null) === 'approved';
        });
    }

    private function createCategory(string $name): Category
    {
        return Category::query()->create([
            'name' => $name.' '.Str::upper(Str::random(4)),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(7)),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
