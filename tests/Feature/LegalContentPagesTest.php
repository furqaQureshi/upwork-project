<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\LegalContentPages;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalContentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_pages_are_available_with_default_real_content(): void
    {
        $this->seed(AppSettingsSeeder::class);

        foreach (LegalContentPages::pages() as $definition) {
            $response = $this->get(route((string) $definition['route']));

            $response->assertOk();
            $response->assertSee((string) $definition['default_title']);
            $response->assertSee('Last updated: March 16, 2026');
        }
    }

    public function test_admin_can_manage_google_play_legal_content_pages(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $indexResponse = $this->actingAs($admin)->get(route('admin.legal-content.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Google Play Legal Pages');

        $payload = [];
        foreach (LegalContentPages::pages() as $definition) {
            $titleKey = (string) $definition['title_key'];
            $contentKey = (string) $definition['content_key'];
            $label = (string) $definition['label'];
            $contentBody = trim((string) str_repeat('This policy outlines user rights, platform responsibilities, and legal obligations in clear language. ', 4));

            $payload[$titleKey] = 'Updated '.$label;
            $payload[$contentKey] = "Updated {$label} for marketplace compliance.\n\n"
                .$contentBody;
        }

        $response = $this->actingAs($admin)->post(route('admin.legal-content.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        foreach (LegalContentPages::pages() as $definition) {
            $titleKey = (string) $definition['title_key'];
            $contentKey = (string) $definition['content_key'];

            $this->assertSame($payload[$titleKey], AppSetting::get($titleKey));
            $this->assertSame($payload[$contentKey], AppSetting::get($contentKey));
        }

        $privacyPage = $this->get(route('legal.privacy'));
        $privacyPage->assertOk();
        $privacyPage->assertSee('Updated Privacy Policy');
        $privacyPage->assertSee('Updated Privacy Policy for marketplace compliance.');
    }
}
