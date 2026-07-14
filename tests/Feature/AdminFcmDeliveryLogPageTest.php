<?php

namespace Tests\Feature;

use App\Models\PushDeliveryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFcmDeliveryLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_fcm_delivery_logs_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Log User',
            'email' => 'log-user@example.test',
        ]);

        PushDeliveryLog::query()->create([
            'user_id' => $user->id,
            'provider' => 'fcm',
            'status' => 'failure',
            'target' => 'fcm-token-abc123',
            'response_status' => 404,
            'error_code' => 'UNREGISTERED',
            'error_message' => 'Requested entity was not found.',
            'payload' => ['title' => 'Test'],
            'response_body' => ['error' => ['status' => 'UNREGISTERED']],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.push-delivery-logs.index'))
            ->assertOk()
            ->assertSee('FCM Delivery Logs')
            ->assertSee('UNREGISTERED')
            ->assertSee('fcm-token-abc123')
            ->assertSee('Log User');
    }

    public function test_admin_can_filter_fcm_delivery_logs_by_status_user_date_and_error_code(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $userOne = User::factory()->create([
            'name' => 'Filter User One',
            'email' => 'filter-one@example.test',
        ]);
        $userTwo = User::factory()->create([
            'name' => 'Filter User Two',
            'email' => 'filter-two@example.test',
        ]);

        $successLog = PushDeliveryLog::query()->create([
            'user_id' => $userOne->id,
            'provider' => 'fcm',
            'status' => 'success',
            'target' => 'token-success-old',
            'response_status' => 200,
            'payload' => ['title' => 'Success old'],
            'response_body' => ['name' => 'message-old'],
        ]);
        $successLog->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        $matchingFailureLog = PushDeliveryLog::query()->create([
            'user_id' => $userOne->id,
            'provider' => 'fcm',
            'status' => 'failure',
            'target' => 'token-failure-match',
            'response_status' => 404,
            'error_code' => 'UNREGISTERED',
            'error_message' => 'Requested entity was not found.',
            'payload' => ['title' => 'Failure match'],
            'response_body' => ['error' => ['status' => 'UNREGISTERED']],
        ]);
        $matchingFailureLog->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->save();

        $otherFailureLog = PushDeliveryLog::query()->create([
            'user_id' => $userTwo->id,
            'provider' => 'fcm',
            'status' => 'failure',
            'target' => 'token-failure-other',
            'response_status' => 401,
            'error_code' => 'UNAUTHENTICATED',
            'error_message' => 'Invalid auth token.',
            'payload' => ['title' => 'Failure other'],
            'response_body' => ['error' => ['status' => 'UNAUTHENTICATED']],
        ]);
        $otherFailureLog->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.push-delivery-logs.index', [
                'status' => 'failure',
                'user_id' => $userOne->id,
                'date_from' => now()->subDays(2)->toDateString(),
                'date_to' => now()->toDateString(),
                'error_code' => 'UNREGISTERED',
            ]))
            ->assertOk()
            ->assertSee('token-failure-match')
            ->assertDontSee('token-success-old')
            ->assertDontSee('token-failure-other');
    }

    public function test_non_admin_cannot_access_fcm_delivery_logs_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.push-delivery-logs.index'))
            ->assertForbidden();
    }
}
