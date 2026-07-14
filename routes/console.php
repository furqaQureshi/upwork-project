<?php

use App\Models\ListingImage;
use App\Services\ListingImageThumbnailService;
use App\Services\AI\SeoRankService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('seo:ai-optimize {--force : Run even when interval has not elapsed}', function (SeoRankService $seoRankService) {
    $result = $seoRankService->runAuditAndOptimize((bool) $this->option('force'));

    if (($result['status'] ?? '') !== 'completed') {
        $this->warn((string) ($result['message'] ?? 'AI SEO audit skipped.'));

        return;
    }

    $score = (int) ($result['score'] ?? 0);
    $provider = (string) ($result['provider'] ?? 'heuristic');
    $applied = (bool) ($result['applied'] ?? false);

    $this->info('AI SEO audit completed successfully.');
    $this->line('Provider: '.$provider);
    $this->line('SEO score: '.$score.'/100');
    $this->line('Applied to live SEO settings: '.($applied ? 'yes' : 'no'));
})->purpose('Run AI SEO audit and optionally auto-apply SEO metadata updates.');

Schedule::command('seo:ai-optimize')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Artisan::command('listings:backfill-thumbnails {--chunk=200 : Number of images processed per batch} {--force : Rebuild thumbnails even if they already exist}', function (ListingImageThumbnailService $thumbnailService) {
    $chunkSize = max(10, (int) $this->option('chunk'));
    $force = (bool) $this->option('force');

    $total = ListingImage::query()->count();
    if ($total === 0) {
        $this->warn('No listing images found.');

        return;
    }

    $processed = 0;
    $generated = 0;
    $skipped = 0;
    $missing = 0;
    $failed = 0;

    $this->info('Starting thumbnail backfill for '.$total.' listing images...');

    ListingImage::query()
        ->orderBy('id')
        ->chunkById($chunkSize, function ($images) use (&$processed, &$generated, &$skipped, &$missing, &$failed, $force, $thumbnailService): void {
            foreach ($images as $image) {
                $processed++;

                $path = trim((string) $image->path, '/');
                if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    $skipped++;
                    continue;
                }

                $thumbPath = trim((string) $image->thumbnail_path, '/');
                if (! $force && $thumbPath !== '' && Storage::disk('public')->exists($thumbPath)) {
                    $skipped++;
                    continue;
                }

                if (! Storage::disk('public')->exists($path)) {
                    $missing++;
                    continue;
                }

                $ok = $thumbnailService->createFromStoragePath($path);
                if ($ok) {
                    $generated++;
                } else {
                    $failed++;
                }
            }

            $this->line("Processed {$processed} images... generated={$generated}, skipped={$skipped}, missing={$missing}, failed={$failed}");
        });

    $this->newLine();
    $this->info('Thumbnail backfill completed.');
    $this->line('Total processed: '.$processed);
    $this->line('Generated: '.$generated);
    $this->line('Skipped: '.$skipped);
    $this->line('Missing originals: '.$missing);
    $this->line('Failed: '.$failed);
})->purpose('Generate 640x640 compressed thumbnails for all existing listing images.');
