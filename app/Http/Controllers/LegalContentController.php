<?php

namespace App\Http\Controllers;

use App\Support\LegalContentPages;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LegalContentController extends Controller
{
    public function terms(): View
    {
        return $this->renderPage('terms-and-conditions');
    }

    public function privacy(): View
    {
        return $this->renderPage('privacy-policy');
    }

    public function refund(): View
    {
        return $this->renderPage('refund-and-cancellation-policy');
    }

    public function contentPolicy(): View
    {
        return $this->renderPage('content-policy');
    }

    public function dataDeletion(): View
    {
        return $this->renderPage('data-deletion-policy');
    }

    private function renderPage(string $slug): View
    {
        $definition = LegalContentPages::definition($slug);
        abort_if($definition === null, 404);

        $siteName = (string) setting('site_name', config('app.name', 'Marketplace'));
        $supportEmail = trim((string) setting('contact_email', ''));
        $supportPhone = trim((string) setting('support_phone', ''));

        $defaultTitle = (string) ($definition['default_title'] ?? 'Legal Page');
        $defaultContent = LegalContentPages::defaultContent($slug, $siteName, $supportEmail, $supportPhone);

        $title = trim((string) setting((string) $definition['title_key'], $defaultTitle));
        if ($title === '') {
            $title = $defaultTitle;
        }

        $content = trim((string) setting((string) $definition['content_key'], $defaultContent));
        if ($content === '') {
            $content = $defaultContent;
        }

        $pages = $this->navigationPages();

        return view('legal.show', [
            'pageSlug' => $slug,
            'pageTitle' => $title,
            'pageSummary' => (string) ($definition['summary'] ?? ''),
            'pageContent' => $content,
            'pages' => $pages,
        ]);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function navigationPages(): Collection
    {
        return collect(LegalContentPages::pages())
            ->map(static function (array $definition, string $slug): array {
                return [
                    'slug' => $slug,
                    'label' => (string) ($definition['label'] ?? $slug),
                    'url' => route((string) $definition['route']),
                ];
            })
            ->values();
    }
}
