<?php

namespace Tests\Feature\Auth;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebasePhoneAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_requires_mobile_login_toggle(): void
    {
        $response = $this->postJson('/login/firebase', [
            'id_token' => 'dummy-token',
            'phone' => '+911234567890',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mobile']);
    }

    public function test_existing_user_can_login_using_firebase_phone_otp(): void
    {
        $this->setSetting('auth_login_mobile_enabled', '1', 'boolean', 'registration');
        $this->setSetting('fcm_api_key', 'fake-api-key', 'string', 'notifications');

        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response([
                'users' => [[
                    'localId' => 'firebase-uid-1',
                    'phoneNumber' => '+911234567890',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create([
            'phone' => '+911234567890',
            'is_blocked' => false,
        ]);

        $response = $this->postJson('/login/firebase', [
            'id_token' => 'dummy-token',
            'phone' => '+911234567890',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('redirect', route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unregistered_mobile_login_redirects_to_register_handoff(): void
    {
        $this->setSetting('auth_login_mobile_enabled', '1', 'boolean', 'registration');
        $this->setSetting('registration_enabled', '1', 'boolean', 'registration');
        $this->setSetting('auth_register_mobile_enabled', '1', 'boolean', 'registration');
        $this->setSetting('fcm_api_key', 'fake-api-key', 'string', 'notifications');

        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response([
                'users' => [[
                    'localId' => 'firebase-uid-handoff',
                    'phoneNumber' => '+911234567890',
                    'email' => '',
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/login/firebase', [
            'id_token' => 'dummy-token',
            'phone' => '+911234567890',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('redirect', route('register', ['auth_mode' => 'mobile'], false))
            ->assertSessionHas('auth.mobile_registration_handoff');

        $this->assertGuest();
    }

    public function test_new_user_can_register_using_firebase_phone_otp(): void
    {
        $this->setSetting('registration_enabled', '1', 'boolean', 'registration');
        $this->setSetting('auth_register_mobile_enabled', '1', 'boolean', 'registration');
        $this->setSetting('fcm_api_key', 'fake-api-key', 'string', 'notifications');

        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response([
                'users' => [[
                    'localId' => 'firebase-uid-2',
                    'phoneNumber' => '+919999888877',
                    'email' => '',
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/register/firebase', [
            'name' => 'Mobile Signup User',
            'phone' => '+919999888877',
            'id_token' => 'dummy-token',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('redirect', route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Mobile Signup User',
            'phone' => '+919999888877',
        ]);
    }

    public function test_mobile_registration_can_complete_using_login_handoff_without_second_otp(): void
    {
        $this->setSetting('registration_enabled', '1', 'boolean', 'registration');
        $this->setSetting('auth_register_mobile_enabled', '1', 'boolean', 'registration');

        $response = $this->withSession([
            'auth.mobile_registration_handoff' => [
                'phone' => '+919111222333',
                'uid' => 'firebase-uid-from-login',
                'email' => '',
                'verified_at' => now()->getTimestamp(),
            ],
        ])->postJson('/register/firebase', [
            'name' => 'Verified Login Handoff User',
            'phone' => '+919111222333',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('redirect', route('dashboard', absolute: false))
            ->assertSessionMissing('auth.mobile_registration_handoff');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Verified Login Handoff User',
            'phone' => '+919111222333',
        ]);
    }

    private function setSetting(string $key, string $value, string $type, string $group): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'label' => $key,
                'description' => '',
            ]
        );
    }
}
