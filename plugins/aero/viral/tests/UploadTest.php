<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\ApplicationService;
use Aero\Viral\Classes\UploadService;
use Illuminate\Validation\ValidationException;
use October\Rain\Exception\ValidationException as OctoberValidation;
use PluginTestCase;

class UploadTest extends PluginTestCase
{
    public function testBaseMimeStripsCodecs(): void
    {
        $this->assertSame('video/webm', UploadService::baseMime('Video/WebM;codecs=vp9,opus'));
        $this->assertSame('', UploadService::baseMime(null));
    }

    public function testSignatures(): void
    {
        $this->assertTrue(UploadService::signatureMatches('video/mp4', "\0\0\0\x18ftypisom"));
        $this->assertTrue(UploadService::signatureMatches('video/webm', "\x1A\x45\xDF\xA3rest"));
        $this->assertTrue(UploadService::signatureMatches('image/jpeg', "\xFF\xD8\xFF\xE0"));
        $this->assertTrue(UploadService::signatureMatches('image/png', "\x89PNG\r\n"));
        $this->assertTrue(UploadService::signatureMatches('image/webp', "RIFF\0\0\0\0WEBPVP8 "));
        $this->assertFalse(UploadService::signatureMatches('video/mp4', random_bytes(32)));
        $this->assertFalse(UploadService::signatureMatches('application/pdf', '%PDF-1.4'));
    }

    public function testUploadInitFailsWithoutStorageConfigured(): void
    {
        config(['filesystems.disks.viral.key' => null, 'filesystems.disks.viral.bucket' => null]);
        $this->expectExceptionMessage('muy pronto');
        UploadService::init('video/mp4', 1000, 'file', 'x');
    }

    public function testSubmitNeedsLinkOrUpload(): void
    {
        $in = ['track' => 'edit', 'level' => 1, 'links' => [], 'uploads' => [], 'tags' => [], 'name' => 'QA Viral', 'email' => 'nos@example.com',
            'phone' => '71234567', 'department' => 'La Paz', 'city' => 'El Alto', 'password' => 'claveSegura123', 'adult' => 1, 'terms' => 1];
        try {
            (new ApplicationService)->submit($in, null);
            $this->fail('Debió exigir una muestra');
        } catch (ValidationException|OctoberValidation $e) {
            $this->assertArrayHasKey('links', $e->errors());
        }
    }
}
