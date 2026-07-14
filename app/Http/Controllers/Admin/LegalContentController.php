<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\LegalContentPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LegalContentController extends Controller
{
    public function index(): View
    {
        $siteName = (string) setting('site_name', config('app.name', 'Marketplace'));
        $supportEmail = trim((string) setting('contact_email', ''));
        $supportPhone = trim((string) setting('support_phone', ''));

        $pages = collect(LegalContentPages::pages())
            ->map(static function (array $definition, string $slug) use ($siteName, $supportEmail, $supportPhone): array {
                $defaultTitle = (string) ($definition['default_title'] ?? Str::headline($slug));
                $defaultContent = LegalContentPages::defaultContent($slug, $siteName, $supportEmail, $supportPhone);

                $title = trim((string) setting((string) $definition['title_key'], $defaultTitle));
                if ($title === '') {
                    $title = $defaultTitle;
                }

                $content = trim((string) setting((string) $definition['content_key'], $defaultContent));
                if ($content === '') {
                    $content = $defaultContent;
                }

                return [
                    'slug' => $slug,
                    'label' => (string) ($definition['label'] ?? Str::headline($slug)),
                    'summary' => (string) ($definition['summary'] ?? ''),
                    'route' => route((string) $definition['route']),
                    'title_key' => (string) $definition['title_key'],
                    'content_key' => (string) $definition['content_key'],
                    'title' => $title,
                    'content' => $content,
                ];
            })
            ->values();

        return view('admin.legal.index', [
            'pages' => $pages,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (LegalContentPages::pages() as $definition) {
            $titleKey = (string) $definition['title_key'];
            $contentKey = (string) $definition['content_key'];

            $rules[$titleKey] = ['required', 'string', 'max:150'];
            $rules[$contentKey] = ['required', 'string', 'min:200', 'max:65000'];
        }

        $validated = $request->validate($rules);

        foreach (LegalContentPages::pages() as $definition) {
            $titleKey = (string) $definition['title_key'];
            $contentKey = (string) $definition['content_key'];
            $label = (string) ($definition['label'] ?? Str::headline($titleKey));

            $titleValue = trim((string) ($validated[$titleKey] ?? ''));
            $contentValue = trim((string) ($validated[$contentKey] ?? ''));

            $this->upsertSetting(
                $titleKey,
                $titleValue,
                'Legal page title: '.$label,
                'Admin-managed title shown on the public legal content page.'
            );

            $this->upsertSetting(
                $contentKey,
                $contentValue,
                'Legal page content: '.$label,
                'Admin-managed plain text legal content shown on the public legal content page.'
            );
        }

        AppSetting::clearCache();

        return back()->with('status', 'Legal and Play Console content pages saved successfully.');
    }

    private function upsertSetting(string $key, string $value, string $label, string $description): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => 'string',
                'group' => 'legal',
                'label' => $label,
                'description' => $description,
            ]
        );
    }
}
