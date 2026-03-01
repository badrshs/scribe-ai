<?php

namespace Badr\ScribeAi\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Handles image optimization: resizing, WebP conversion, and storage.
 */
class ImageOptimizer
{
    /**
     * Optimize an uploaded file and store it.
     */
    public function optimizeAndStore(UploadedFile $file, ?string $directory = null, ?int $maxWidth = null, ?int $quality = null): string
    {
        $directory ??= config('scribe-ai.images.directory', 'articles');
        $maxWidth ??= (int) config('scribe-ai.images.max_width', 1600);
        $quality ??= (int) config('scribe-ai.images.quality', 82);
        $disk = config('scribe-ai.images.disk', 'public');

        $path = $file->store($directory, $disk);
        if (! $path) {
            throw new RuntimeException('Failed to store uploaded image');
        }

        return $this->processImage($path, $disk, $maxWidth, $quality);
    }

    /**
     * Optimize an existing image already on disk.
     */
    public function optimizeExisting(string $relativePath, ?int $maxWidth = null, ?int $quality = null, bool $replace = true): string
    {
        $maxWidth ??= (int) config('scribe-ai.images.max_width', 1600);
        $quality ??= (int) config('scribe-ai.images.quality', 82);
        $disk = config('scribe-ai.images.disk', 'public');
        $minSize = (int) config('scribe-ai.images.min_size_for_conversion', 20480);

        $fullPath = Storage::disk($disk)->path($relativePath);
        if (! file_exists($fullPath)) {
            throw new RuntimeException("Image not found: {$relativePath}");
        }

        $fileSize = filesize($fullPath);
        if ($fileSize < $minSize && str_ends_with(strtolower($relativePath), '.webp')) {
            Log::info('Image already optimized, skipping', ['path' => $relativePath]);

            return $relativePath;
        }

        return $this->processImage($relativePath, $disk, $maxWidth, $quality, $replace);
    }

    protected function processImage(string $relativePath, string $disk, int $maxWidth, int $quality, bool $replace = true): string
    {
        $fullPath = Storage::disk($disk)->path($relativePath);
        $imageInfo = @getimagesize($fullPath);

        if (! $imageInfo) {
            Log::warning('Could not read image info, skipping optimization', ['path' => $relativePath]);

            return $relativePath;
        }

        [$width, $height, $type] = $imageInfo;

        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($fullPath),
            IMAGETYPE_PNG => imagecreatefrompng($fullPath),
            IMAGETYPE_GIF => imagecreatefromgif($fullPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($fullPath),
            default => null,
        };

        if (! $source) {
            Log::warning('Unsupported image type for optimization', ['path' => $relativePath, 'type' => $type]);

            return $relativePath;
        }

        if ($width > $maxWidth) {
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            unset($source);
            $source = $resized;
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $relativePath);
        $webpFullPath = Storage::disk($disk)->path($webpPath);

        imagewebp($source, $webpFullPath, $quality);
        unset($source);

        $optimizedSize = filesize($webpFullPath);
        $originalSize = file_exists($fullPath) ? filesize($fullPath) : null;

        if ($replace && $webpPath !== $relativePath) {
            Storage::disk($disk)->delete($relativePath);
        }

        Log::info('Image optimized', [
            'original' => $relativePath,
            'optimized' => $webpPath,
            'original_size' => $originalSize,
            'optimized_size' => $optimizedSize,
        ]);

        return $webpPath;
    }
}
