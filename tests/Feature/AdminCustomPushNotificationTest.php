<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\AdminCustomPushNotification;
use App\Services\WebPush\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminCustomPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_custom_push_notification_composer(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $recipient = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.push-notifications.create', ['user' => $recipient->id]))
            ->assertOk()
            ->assertSee('Custom Push Notification')
            ->assertSee($recipient->email);
    }

    public function test_admin_can_send_custom_push_notification_to_specific_user(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
            'name' => 'Push Admin',
        ]);
        $recipient = User::factory()->create();

        $this->createActivePushSubscription($recipient);

        $this->mock(WebPushService::class, function (MockInterface $mock) use ($recipient): void {
            $mock->shouldReceive('sendToUser')
                ->once()
                ->withArgs(function (User $user, array $payload) use ($recipient): bool {
                    return $user->is($recipient)
                        && ($payload['title'] ?? null) === 'Flash Sale'
                        && (($payload['data']['url'] ?? null) === '/offers/today');
                });
        });

        $this->actingAs($admin)
            ->post(route('admin.push-notifications.store'), [
                'audience' => 'specific_user',
                'user_id' => $recipient->id,
                'title' => 'Flash Sale',
                'body' => 'Today only: extra visibility on featured ads.',
                'url' => '/offers/today',
            ])
            ->assertRedirect(route('admin.push-notifications.create'))
            ->assertSessionHas('status', 'Custom notification sent to 1 user(s) across 1 active device(s).');

        Notification::assertSentTo($recipient, AdminCustomPushNotification::class, function (AdminCustomPushNotification $notification, array $channels) use ($recipient): bool {
            $data = $notification->toArray($recipient);

            return in_array('database', $channels, true)
                && ($data['title'] ?? null) === 'Flash Sale'
                && ($data['url'] ?? null) === '/offers/today';
        });
    }

    public function test_admin_can_send_custom_push_notification_to_all_sellers(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        [$sellerOne] = $this->createSellerWithListing('Seller One');
        [$sellerTwo] = $this->createSellerWithListing('Seller Two');
        $normalUser = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->createActivePushSubscription($sellerOne);
        $this->createActivePushSubscription($sellerTwo);

        $this->mock(WebPushService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToUser')->twice();
        });

        $this->actingAs($admin)
            ->post(route('admin.push-notifications.store'), [
                'audience' => 'sellers',
                'title' => 'Seller Update',
                'body' => 'Remember to refresh your listings before the weekend rush.',
                'url' => '/listings',
            ])
            ->assertRedirect(route('admin.push-notifications.create'))
            ->assertSessionHas('status', 'Custom notification sent to 2 user(s) across 2 active device(s).');

        Notification::assertSentTo($sellerOne, AdminCustomPushNotification::class);
        Notification::assertSentTo($sellerTwo, AdminCustomPushNotification::class);
        Notification::assertNotSentTo($normalUser, AdminCustomPushNotification::class);
    }

    public function test_non_admin_cannot_access_custom_push_notification_routes(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.push-notifications.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.push-notifications.store'), [
                'audience' => 'all_users',
                'title' => 'Blocked',
                'body' => 'Should not be sent.',
            ])
            ->assertForbidden();
    }

    private function createActivePushSubscription(User $user): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/'.Str::lower(Str::random(20)),
            'public_key' => Str::random(64),
            'auth_token' => Str::random(32),
            'content_encoding' => 'aesgcm',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: Listing}
     */
    private function createSellerWithListing(string $sellerName): array
    {
        $seller = User::factory()->create([
            'name' => $sellerName,
            'is_admin' => false,
        ]);

        $category = Category::query()->create([
            'name' => 'Push Seller Category '.Str::upper(Str::random(4)),
            'slug' => 'push-seller-category-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $listing = Listing::query()->create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Seller Listing '.Str::upper(Str::random(4)),
            'slug' => Str::slug('seller-listing-'.Str::random(8)),
            'description' => 'Listing created to test seller broadcast notifications.',
            'price' => 25000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        return [$seller, $listing];
    }
}
