<?php

namespace App\Infrastructure\Export;

/**
 * GD-based image compression — no third-party dependencies.
 * Reduces image size for web delivery while maintaining reasonable quality.
 */
class ImageCompressor
{
    public const MAX_WIDTH = 1200;
    public const MAX_HEIGHT = 1200;
    public const JPEG_QUALITY = 75;
    public const WEBP_QUALITY = 72;

    /**
     * Compress an image file in-place. Returns new size in bytes.
     */
    public static function compress(string $path, int $maxWidth = self::MAX_WIDTH, int $maxHeight = self::MAX_HEIGHT): int
    {
        if (!extension_loaded('gd')) return filesize($path);

        $info = @getimagesize($path);
        if (!$info) return filesize($path);

        [$origW, $origH, $type] = $info;

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => null,
        };

        if (!$image) return filesize($path);

        // Resize if needed
        $ratio = min($maxWidth / $origW, $maxHeight / $origH, 1);
        $newW = (int) round($origW * $ratio);
        $newH = (int) round($origH * $ratio);

        if ($ratio < 1) {
            $resized = imagecreatetruecolor($newW, $newH);
            // Preserve transparency for PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($image);
            $image = $resized;
        }

        // Save compressed
        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, self::JPEG_QUALITY),
            IMAGETYPE_PNG  => imagepng($image, $path, 6),
            IMAGETYPE_WEBP => imagewebp($image, $path, self::WEBP_QUALITY),
            default        => null,
        };

        imagedestroy($image);

        return filesize($path);
    }
}
