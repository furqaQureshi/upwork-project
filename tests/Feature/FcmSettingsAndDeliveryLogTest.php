<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\PushDeliveryLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPush\WebPushService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmSettingsAndDeliveryLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_fcm_credentials_from_notifications_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'notifications',
            'notification_poll_seconds' => '20',
            'notification_email_enabled' => '1',
            'notification_new_message' => '1',
            'notification_listing_approved' => '1',
            'notification_listing_expired' => '1',
            'notification_push_enabled' => '1',
            'fcm_api_key' => 'test-web-api-key',
            'fcm_project_id' => 'unisell-fcm-test-project',
            'fcm_messaging_sender_id' => '123456789000',
            'fcm_app_id' => '1:123456789000:web:abc1234def5678',
            'fcm_vapid_key' => 'BLe2hYJusY-test-vapid-key',
            'fcm_service_account_email' => 'firebase-adminsdk@unisell-fcm-test-project.iam.gserviceaccount.com',
            'fcm_service_account_private_key' => "-----BEGIN PRIVATE KEY-----\nTESTKEY\n-----END PRIVATE KEY-----",
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('test-web-api-key', AppSetting::get('fcm_api_key'));
        $this->assertSame('unisell-fcm-test-project', AppSetting::get('fcm_project_id'));
        $this->assertSame('123456789000', AppSetting::get('fcm_messaging_sender_id'));
        $this->assertSame('1:123456789000:web:abc1234def5678', AppSetting::get('fcm_app_id'));
        $this->assertSame('BLe2hYJusY-test-vapid-key', AppSetting::get('fcm_vapid_key'));
        $this->assertSame('firebase-adminsdk@unisell-fcm-test-project.iam.gserviceaccount.com', AppSetting::get('fcm_service_account_email'));
        $this->assertSame("-----BEGIN PRIVATE KEY-----\nTESTKEY\n-----END PRIVATE KEY-----", AppSetting::get('fcm_service_account_private_key'));
    }

    public function test_blank_fcm_secret_fields_keep_existing_values(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('fcm_api_key', 'existing-api-key');
        AppSetting::set('fcm_service_account_private_key', "-----BEGIN PRIVATE KEY-----\nEXISTING\n-----END PRIVATE KEY-----");

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'notifications',
            'notification_poll_seconds' => '20',
            'notification_email_enabled' => '1',
            'notification_new_message' => '1',
            'notification_listing_approved' => '1',
            'notification_listing_expired' => '1',
            'notification_push_enabled' => '1',
            'fcm_api_key' => '',
            'fcm_service_account_private_key' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('existing-api-key', AppSetting::get('fcm_api_key'));
        $this->assertSame("-----BEGIN PRIVATE KEY-----\nEXISTING\n-----END PRIVATE KEY-----", AppSetting::get('fcm_service_account_private_key'));
    }

    public function test_admin_can_upload_fcm_service_account_json_to_auto_fill_keys(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $serviceAccountJson = [
            'type' => 'service_account',
            'project_id' => 'unisell-uploaded-project',
            'private_key_id' => 'test-key-id',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nUPLOADEDKEY\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk@unisell-uploaded-project.iam.gserviceaccount.com',
            'client_id' => '1234567890',
        ];

        $uploadedJson = UploadedFile::fake()->createWithContent(
            'service-account.json',
            json_encode($serviceAccountJson, JSON_UNESCAPED_SLASHES)
        );

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'notifications',
            'notification_poll_seconds' => '20',
            'notification_email_enabled' => '1',
            'notification_new_message' => '1',
            'notification_listing_approved' => '1',
            'notification_listing_expired' => '1',
            'notification_push_enabled' => '1',
            'fcm_service_account_json_file' => $uploadedJson,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('unisell-uploaded-project', AppSetting::get('fcm_project_id'));
        $this->assertSame('firebase-adminsdk@unisell-uploaded-project.iam.gserviceaccount.com', AppSetting::get('fcm_service_account_email'));
        $this->assertSame("-----BEGIN PRIVATE KEY-----\nUPLOADEDKEY\n-----END PRIVATE KEY-----", trim((string) AppSetting::get('fcm_service_account_private_key')));
    }

    public function test_fcm_delivery_logs_successful_sends(): void
    {
        [$user, $subscription] = $this->createFcmSubscription();
        $this->configureFcmSettings();

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'https://fcm.googleapis.com/v1/projects/unisell-fcm-test-project/messages:send') {
                return Http::response([
                    'name' => 'projects/unisell-fcm-test-project/messages/12345',
                ], 200);
            }

            return Http::response([], 404);
        });

        (new WebPushService())->sendToUser($user, [
            'title' => 'Delivery Success',
            'body' => 'FCM delivery success test',
            'data' => [
                'url' => '/chat',
            ],
        ]);

        $log = PushDeliveryLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($subscription->id, $log->push_subscription_id);
        $this->assertSame('fcm', $log->provider);
        $this->assertSame('success', $log->status);
        $this->assertSame(200, $log->response_status);
        $this->assertNull($log->error_code);
        $this->assertNotNull($log->delivered_at);
    }

    public function test_fcm_delivery_logs_failure_and_deactivates_unregistered_token(): void
    {
        [$user, $subscription] = $this->createFcmSubscription('fcm-token-failure-123');
        $this->configureFcmSettings();

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'https://fcm.googleapis.com/v1/projects/unisell-fcm-test-project/messages:send') {
                return Http::response([
                    'error' => [
                        'code' => 404,
                        'status' => 'UNREGISTERED',
                        'message' => 'Requested entity was not found.',
                    ],
                ], 404);
            }

            return Http::response([], 404);
        });

        (new WebPushService())->sendToUser($user, [
            'title' => 'Delivery Failure',
            'body' => 'FCM delivery failure test',
            'data' => [
                'url' => '/chat',
            ],
        ]);

        $subscription->refresh();
        $this->assertFalse($subscription->is_active);

        $log = PushDeliveryLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('failure', $log->status);
        $this->assertSame(404, $log->response_status);
        $this->assertSame('UNREGISTERED', $log->error_code);
        $this->assertSame('Requested entity was not found.', $log->error_message);
        $this->assertNull($log->delivered_at);
    }

    /**
     * @return array{0: User, 1: PushSubscription}
     */
    private function createFcmSubscription(string $token = 'fcm-token-success-123'): array
    {
        $user = User::factory()->create();

        $subscription = PushSubscription::query()->create([
            'user_id' => $user->id,
            'provider' => 'fcm',
            'endpoint' => 'fcm:'.$token,
            'device_token' => $token,
            'public_key' => 'fcm',
            'auth_token' => 'fcm',
            'content_encoding' => 'fcm',
            'is_active' => true,
        ]);

        return [$user, $subscription];
    }

    private function configureFcmSettings(): void
    {
        config()->set('services.fcm.project_id', '');
        config()->set('services.fcm.service_account_email', '');
        config()->set('services.fcm.service_account_private_key', '');

        AppSetting::updateOrCreate(['key' => 'fcm_project_id'], [
            'value' => 'unisell-fcm-test-project',
            'type' => 'string',
            'group' => 'notifications',
            'label' => 'FCM project ID',
        ]);

        AppSetting::updateOrCreate(['key' => 'fcm_service_account_email'], [
            'value' => 'firebase-adminsdk@unisell-fcm-test-project.iam.gserviceaccount.com',
            'type' => 'string',
            'group' => 'notifications',
            'label' => 'FCM service account email',
        ]);

        AppSetting::updateOrCreate(['key' => 'fcm_service_account_private_key'], [
            'value' => 'test-private-key',
            'type' => 'string',
            'group' => 'notifications',
            'label' => 'FCM service account private key',
        ]);

        AppSetting::clearCache();
        Cache::put('fcm.push.access_token', 'cached-oauth-token', now()->addMinutes(45));
    }
}
