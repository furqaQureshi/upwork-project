<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ListingImageThumbnailService
{
    public function createFromUploadedFile(UploadedFile $image, string $storedPath): bool
    {
        $sourceBinary = @file_get_contents((string) $image->getRealPath());
        if ($sourceBinary === false) {
            return false;
        }

        return $this->createFromBinary($sourceBinary, $storedPath);
    }

    public function createFromStoragePath(string $storedPath): bool
    {
        $normalized = trim($storedPath, '/');
        if ($normalized === '' || ! Storage::disk('public')->exists($normalized)) {
            return false;
        }

        $sourceBinary = Storage::disk('public')->get($normalized);
        if ($sourceBinary === '') {
            return false;
        }

        return $this->createFromBinary($sourceBinary, $normalized);
    }

    public function thumbnailPathFromStoredPath(string $storedPath): string
    {
        $normalized = trim($storedPath, '/');
        $directory = pathinfo($normalized, PATHINFO_DIRNAME);
        $filename = pathinfo($normalized, PATHINFO_FILENAME);
        $baseDirectory = $directory === '.' ? '' : $directory.'/';

        return $baseDirectory.'thumbs/'.$filename.'.jpg';
    }

    private function createFromBinary(string $sourceBinary, string $storedPath): bool
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return false;
        }

        $sourceImage = @imagecreatefromstring($sourceBinary);
        if (! is_resource($sourceImage) && ! ($sourceImage instanceof \GdImage)) {
            return false;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($sourceImage);

            return false;
        }

        $targetSize = 640;
        $scale = max($targetSize / $sourceWidth, $targetSize / $sourceHeight);
        $scaledWidth = (int) ceil($sourceWidth * $scale);
        $scaledHeight = (int) ceil($sourceHeight * $scale);

        $thumbnail = imagecreatetruecolor($targetSize, $targetSize);
        if (! $thumbnail) {
            imagedestroy($sourceImage);

            return false;
        }

        imagecopyresampled(
            $thumbnail,
            $sourceImage,
            (int) floor(($targetSize - $scaledWidth) / 2),
            (int) floor(($targetSize - $scaledHeight) / 2),
            0,
            0,
            $scaledWidth,
            $scaledHeight,
            $sourceWidth,
            $sourceHeight,
        );

        $thumbnailPath = $this->thumbnailPathFromStoredPath($storedPath);
        $thumbnailDirectory = pathinfo($thumbnailPath, PATHINFO_DIRNAME);
        if ($thumbnailDirectory !== '' && $thumbnailDirectory !== '.') {
            Storage::disk('public')->makeDirectory($thumbnailDirectory);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'listing-thumb-');
        if ($temporaryPath === false) {
            imagedestroy($thumbnail);
            imagedestroy($sourceImage);

            return false;
        }

        imagejpeg($thumbnail, $temporaryPath, 78);
        $thumbnailBinary = @file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        imagedestroy($thumbnail);
        imagedestroy($sourceImage);

        if ($thumbnailBinary === false || $thumbnailBinary === '') {
            return false;
        }

        Storage::disk('public')->put($thumbnailPath, $thumbnailBinary);

        return true;
    }
}
