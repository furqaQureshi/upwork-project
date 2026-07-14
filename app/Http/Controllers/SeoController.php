<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            [
                'loc' => route('home'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'hourly',
                'priority' => '1.0',
            ],
            [
                'loc' => route('categories.index'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ],
            [
                'loc' => route('menu.index'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.7',
            ],
            [
                'loc' => route('legal.terms'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
            [
                'loc' => route('legal.privacy'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
            [
                'loc' => route('legal.refund'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
            [
                'loc' => route('legal.content-policy'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
            [
                'loc' => route('legal.data-deletion'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
        ];

        $listingUrls = Listing::query()
            ->approved()
            ->whereNotNull('published_at')
            ->latest('updated_at')
            ->limit(10000)
            ->get(['slug', 'updated_at', 'published_at'])
            ->map(static function (Listing $listing): array {
                $lastModified = $listing->updated_at ?? $listing->published_at ?? now();

                return [
                    'loc' => route('listings.show', $listing->slug),
                    'lastmod' => $lastModified->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '0.7',
                ];
            })
            ->all();

        $allUrls = array_merge($urls, $listingUrls);

        return response()
            ->view('seo.sitemap', ['urls' => $allUrls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $robotsDirective = strtolower(trim((string) setting('seo_robots', 'index,follow')));
        $allowIndexing = ! str_contains($robotsDirective, 'noindex');

        $lines = [
            'User-agent: *',
            $allowIndexing ? 'Allow: /' : 'Disallow: /',
            'Disallow: /admin',
            'Disallow: /chat',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function adsTxt(): Response
    {
        $clientId = trim((string) setting('adsense_client_id', ''));

        if (preg_match('/^ca-pub-(\d+)$/', $clientId, $matches) !== 1) {
            return response("# Configure a valid AdSense publisher ID in admin settings.\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response('google.com, pub-'.($matches[1] ?? '').", DIRECT, f08c47fec0942fa0\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
