<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $siteName = (string) setting('site_name', config('app.name', 'Unsell'));
        $name = trim((string) setting('twa_name', ''));
        $shortName = trim((string) setting('twa_short_name', ''));
        $description = trim((string) setting('twa_description', ''));

        $display = strtolower(trim((string) setting('twa_display', 'standalone')));
        if (! in_array($display, ['fullscreen', 'standalone', 'minimal-ui', 'browser'], true)) {
            $display = 'standalone';
        }

        $orientation = strtolower(trim((string) setting('twa_orientation', 'any')));
        if (! in_array($orientation, ['any', 'natural', 'landscape', 'landscape-primary', 'landscape-secondary', 'portrait', 'portrait-primary', 'portrait-secondary'], true)) {
            $orientation = 'any';
        }

        $themeColor = $this->normalizeHexColor((string) setting('twa_theme_color', '#f97316'), '#f97316');
        $backgroundColor = $this->normalizeHexColor((string) setting('twa_background_color', '#ffffff'), '#ffffff');

        $rawIconUrl      = trim((string) setting('twa_icon_url', ''));
        $rawMaskableUrl  = trim((string) setting('twa_icon_maskable_url', ''));

        $resolveIconUrl = static function (string $url): string {
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }

            return Storage::url($url);
        };

        $icons = [];
        if ($rawIconUrl !== '') {
            $icons[] = ['src' => $resolveIconUrl($rawIconUrl), 'sizes' => 'any', 'type' => 'image/png', 'purpose' => 'any'];
        } else {
            $icons[] = ['src' => '/branding/unsell-icon-512.png', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'];
        }
        if ($rawMaskableUrl !== '') {
            $icons[] = ['src' => $resolveIconUrl($rawMaskableUrl), 'sizes' => 'any', 'type' => 'image/png', 'purpose' => 'maskable'];
        } else {
            $icons[] = ['src' => '/branding/unsell-maskable-512.png', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'];
        }

        $manifest = [
            'name' => $name !== '' ? $name : $siteName.' Marketplace',
            'short_name' => $shortName !== '' ? $shortName : $siteName,
            'description' => $description !== ''
                ? $description
                : (string) setting('seo_meta_description', 'Buy and sell items nearby with app-like marketplace experience.'),
            'start_url' => $this->normalizeManifestPath((string) setting('twa_start_url', '/')),
            'scope' => $this->normalizeManifestPath((string) setting('twa_scope', '/')),
            'display' => $display,
            'orientation' => $orientation,
            'background_color' => $backgroundColor,
            'theme_color' => $themeColor,
            'categories' => ['shopping', 'lifestyle', 'business'],
            'lang' => 'en-IN',
            'icons' => $icons,
        ];

        return response()
            ->json($manifest)
            ->header('Content-Type', 'application/manifest+json; charset=utf-8');
    }

    public function assetLinks(): JsonResponse
    {
        $enabled = (bool) setting('twa_enabled', false);
        $packageName = trim((string) setting('twa_package_name', ''));

        $fingerprintSetting = setting('twa_sha256_fingerprints', []);
        if (is_string($fingerprintSetting)) {
            $decodedFingerprints = json_decode($fingerprintSetting, true);
            if (is_array($decodedFingerprints)) {
                $fingerprintSetting = $decodedFingerprints;
            }
        }

        $fingerprints = $this->normalizeFingerprints((array) $fingerprintSetting);

        $packageNameIsValid = preg_match('/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$/', $packageName) === 1;

        if (! $enabled || ! $packageNameIsValid || $fingerprints === []) {
            return response()->json([]);
        }

        return response()->json([
            [
                'relation' => [
                    'delegate_permission/common.handle_all_urls',
                ],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $packageName,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ]);
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        $aasaPath = public_path('.well-known/apple-app-site-association');

        if (! file_exists($aasaPath)) {
            return response()->json([])->header('Content-Type', 'application/json');
        }

        $contents = file_get_contents($aasaPath);
        $data = json_decode($contents, true);

        if (! is_array($data)) {
            return response()->json([])->header('Content-Type', 'application/json');
        }

        return response()->json($data)->header('Content-Type', 'application/json');
    }

    private function normalizeManifestPath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return '/';
        }

        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            return $trimmed;
        }

        return '/'.ltrim($trimmed, '/');
    }

    private function normalizeHexColor(string $value, string $fallback): string
    {
        $trimmed = trim($value);

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $trimmed) !== 1) {
            return $fallback;
        }

        return strtoupper($trimmed);
    }

    /**
     * @param  array<int, mixed>  $fingerprints
     * @return array<int, string>
     */
    private function normalizeFingerprints(array $fingerprints): array
    {
        $normalized = [];

        foreach ($fingerprints as $fingerprint) {
            $value = strtoupper(trim((string) $fingerprint));

            if ($value === '') {
                continue;
            }

            if (preg_match('/^[A-F0-9]{2}(?::[A-F0-9]{2}){31}$/', $value) !== 1) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}