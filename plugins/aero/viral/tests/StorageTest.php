<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\ViralStorage;
use PluginTestCase;

class StorageTest extends PluginTestCase
{
    public function testKeysAreRandomPrivateAndSafe(): void
    {
        $a = ViralStorage::samplePath('uuid-1', 'MP4');
        $b = ViralStorage::samplePath('uuid-1', 'mp4');
        $this->assertStringStartsWith('samples/uuid-1/', $a);
        $this->assertStringEndsWith('.mp4', $a);
        $this->assertNotSame($a, $b);
        $this->assertStringEndsWith('.bin', ViralStorage::samplePath('u', 'php'));      // extensión desconocida
        $this->assertStringEndsWith('.bin', ViralStorage::samplePath('u', '../../x'));  // sin traversal

        $k = ViralStorage::kycPath('uuid-1', 'front', 'JPG');
        $this->assertMatchesRegularExpression('#^kyc/uuid-1/front-[a-z0-9]{12}\.jpg$#', $k);
    }

    public function testKycRejectsUnknownPart(): void
    {
        $this->expectException(\Throwable::class);
        ViralStorage::kycPath('uuid-1', '../etc');
    }

    public function testFullKeyUsesConfiguredPrefix(): void
    {
        config(['filesystems.disks.viral.root' => 'viral']);
        $this->assertSame('viral/kyc/x.jpg', ViralStorage::fullKey('kyc/x.jpg'));
        config(['filesystems.disks.viral.root' => '']);
        $this->assertSame('kyc/x.jpg', ViralStorage::fullKey('/kyc/x.jpg'));
    }

    public function testNotConfiguredWithoutCredentials(): void
    {
        config(['filesystems.disks.viral.key' => null, 'filesystems.disks.viral.bucket' => null]);
        $this->assertFalse(ViralStorage::isConfigured());
    }
}
