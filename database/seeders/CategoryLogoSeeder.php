<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryLogoSeeder extends Seeder
{
    public function run(): void
    {
        $palette = [
            ['#f97316', '#fb923c'],
            ['#0ea5e9', '#38bdf8'],
            ['#10b981', '#34d399'],
            ['#8b5cf6', '#a78bfa'],
            ['#ef4444', '#f87171'],
            ['#f59e0b', '#fbbf24'],
            ['#14b8a6', '#2dd4bf'],
            ['#6366f1', '#818cf8'],
        ];

        $categories = Category::query()->orderBy('id')->get();

        foreach ($categories as $index => $category) {
            $abbr = $this->abbreviation($category->name);
            $colors = $palette[$index % count($palette)];

            $slug = Str::slug($category->name);
            if ($slug === '') {
                $slug = 'category-'.$category->id;
            }

            $path = 'category-icons/'.$slug.'-'.$category->id.'.svg';

            Storage::disk('public')->put(
                $path,
                $this->svgLogo($abbr, $colors[0], $colors[1])
            );

            $category->icon = $path;
            $category->save();
        }

        $this->command->info('Category logos updated for '.$categories->count().' categories.');
    }

    private function abbreviation(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        if ($parts === []) {
            return 'CT';
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
    }

    private function svgLogo(string $abbr, string $fromColor, string $toColor): string
    {
        $safeAbbr = htmlspecialchars($abbr, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512" role="img" aria-label="{$safeAbbr}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$fromColor}"/>
      <stop offset="100%" stop-color="{$toColor}"/>
    </linearGradient>
  </defs>
  <rect x="24" y="24" width="464" height="464" rx="112" fill="url(#g)"/>
  <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle"
        fill="#ffffff" font-size="180" font-family="Arial, Helvetica, sans-serif" font-weight="700">{$safeAbbr}</text>
</svg>
SVG;
    }
}
