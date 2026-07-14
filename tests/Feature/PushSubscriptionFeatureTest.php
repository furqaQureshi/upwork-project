<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_fcm_push_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'provider' => 'fcm',
            'token' => 'fcm-test-token-123456',
            'permission' => 'granted',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'provider' => 'fcm',
            'device_token' => 'fcm-test-token-123456',
            'endpoint' => 'fcm:fcm-test-token-123456',
            'is_active' => true,
        ]);
    }

    public function test_user_can_deactivate_fcm_push_token(): void
    {
        $user = User::factory()->create();

        $user->pushSubscriptions()->create([
            'provider' => 'fcm',
            'endpoint' => 'fcm:fcm-token-to-disable',
            'device_token' => 'fcm-token-to-disable',
            'public_key' => 'fcm',
            'auth_token' => 'fcm',
            'content_encoding' => 'fcm',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->deleteJson(route('push-subscriptions.destroy'), [
            'token' => 'fcm-token-to-disable',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'provider' => 'fcm',
            'device_token' => 'fcm-token-to-disable',
            'is_active' => false,
        ]);
    }

    public function test_legacy_webpush_subscription_payload_is_still_supported(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'https://push.example.test/subscription/abc123',
            'keys' => [
                'p256dh' => 'public-key-123',
                'auth' => 'auth-key-123',
            ],
            'contentEncoding' => 'aesgcm',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'provider' => 'webpush',
            'endpoint' => 'https://push.example.test/subscription/abc123',
            'public_key' => 'public-key-123',
            'auth_token' => 'auth-key-123',
            'content_encoding' => 'aesgcm',
            'is_active' => true,
        ]);
    }
}
