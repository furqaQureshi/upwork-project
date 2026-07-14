<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function countries(): JsonResponse
    {
        $catalog = $this->catalog();
        $items = [];

        foreach ($catalog as $code => $country) {
            $items[] = [
                'code' => (string) $code,
                'name' => (string) ($country['name'] ?? $code),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return response()->json(['items' => $items]);
    }

    public function states(Request $request): JsonResponse
    {
        $country = $this->countryFromRequest($request);
        $states = array_keys((array) ($country['states'] ?? []));
        sort($states);

        return response()->json([
            'items' => array_map(fn (string $name): array => ['name' => $name], $states),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $country = $this->countryFromRequest($request);
        $stateName = $this->normalizedName($request->query('state'));

        $states = (array) ($country['states'] ?? []);
        $cities = array_keys((array) ($states[$stateName] ?? []));
        sort($cities);

        return response()->json([
            'items' => array_map(fn (string $name): array => ['name' => $name], $cities),
        ]);
    }

    public function areas(Request $request): JsonResponse
    {
        $country = $this->countryFromRequest($request);
        $stateName = $this->normalizedName($request->query('state'));
        $cityName = $this->normalizedName($request->query('city'));

        $states = (array) ($country['states'] ?? []);
        $cities = (array) ($states[$stateName] ?? []);
        $areas = (array) ($cities[$cityName] ?? []);

        $items = [];
        foreach ($areas as $area) {
            if (is_array($area)) {
                $items[] = [
                    'name' => (string) ($area['name'] ?? ''),
                    'latitude' => isset($area['latitude']) ? (float) $area['latitude'] : null,
                    'longitude' => isset($area['longitude']) ? (float) $area['longitude'] : null,
                ];

                continue;
            }

            $name = trim((string) $area);
            if ($name === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'latitude' => null,
                'longitude' => null,
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return response()->json(['items' => $items]);
    }

    private function catalog(): array
    {
        return (array) config('location_catalog', []);
    }

    private function countryFromRequest(Request $request): array
    {
        $countryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $request->query('country', '')) ?? '', 0, 2));
        $catalog = $this->catalog();

        return (array) ($catalog[$countryCode] ?? []);
    }

    private function normalizedName(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 120);
    }
}