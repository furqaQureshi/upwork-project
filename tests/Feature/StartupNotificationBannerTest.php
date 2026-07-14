<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartupNotificationBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_notification_denied_banner_markup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Notifications are blocked in browser settings.');
    }
}
