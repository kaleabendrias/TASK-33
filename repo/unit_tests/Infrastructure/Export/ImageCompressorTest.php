<?php

namespace UnitTests\Infrastructure\Export;

use App\Infrastructure\Export\ImageCompressor;
use PHPUnit\Framework\TestCase;

class ImageCompressorTest extends TestCase
{
    public function test_compress_non_image_returns_original_size(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ic');
        file_put_contents($tmp, 'not an image');
        $size = ImageCompressor::compress($tmp);
        $this->assertEquals(filesize($tmp), $size);
        unlink($tmp);
    }

    public function test_compress_jpeg_image(): void
    {
        if (!extension_loaded('gd')) $this->markTestSkipped('GD not available');
        $tmp = tempnam(sys_get_temp_dir(), 'jpg') . '.jpg';
        $img = imagecreatetruecolor(200, 200);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        imagejpeg($img, $tmp, 100);
        imagedestroy($img);
        $origSize = filesize($tmp);
        $newSize = ImageCompressor::compress($tmp);
        $this->assertFileExists($tmp);
        $this->assertGreaterThan(0, $newSize);
        unlink($tmp);
    }

    public function test_compress_png_image(): void
    {
        if (!extension_loaded('gd')) $this->markTestSkipped('GD not available');
        $tmp = tempnam(sys_get_temp_dir(), 'png') . '.png';
        $img = imagecreatetruecolor(100, 100);
        imagepng($img, $tmp);
        imagedestroy($img);
        $newSize = ImageCompressor::compress($tmp);
        $this->assertGreaterThan(0, $newSize);
        unlink($tmp);
    }

    public function test_compress_large_image_resizes(): void
    {
        if (!extension_loaded('gd')) $this->markTestSkipped('GD not available');
        $tmp = tempnam(sys_get_temp_dir(), 'big') . '.jpg';
        $img = imagecreatetruecolor(2400, 1800);
        imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 255));
        imagejpeg($img, $tmp, 100);
        imagedestroy($img);
        ImageCompressor::compress($tmp, 800, 600);
        $info = getimagesize($tmp);
        $this->assertLessThanOrEqual(800, $info[0]);
        $this->assertLessThanOrEqual(600, $info[1]);
        unlink($tmp);
    }

    public function test_nonexistent_file(): void
    {
        $size = @ImageCompressor::compress('/tmp/nonexistent_file_' . mt_rand());
        $this->assertLessThanOrEqual(0, $size);
    }

    public function test_constants(): void
    {
        $this->assertEquals(1200, ImageCompressor::MAX_WIDTH);
        $this->assertEquals(1200, ImageCompressor::MAX_HEIGHT);
        $this->assertEquals(75, ImageCompressor::JPEG_QUALITY);
        $this->assertEquals(72, ImageCompressor::WEBP_QUALITY);
    }
}
