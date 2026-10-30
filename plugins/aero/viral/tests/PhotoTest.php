<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\PhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use October\Rain\Exception\ApplicationException;
use PluginTestCase;

class PhotoTest extends PluginTestCase
{
    protected function png(int $w, int $h): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        $f = tempnam(sys_get_temp_dir(), 'ph') . '.png';
        imagepng($im, $f);

        return new UploadedFile($f, 'foto.png', 'image/png', null, true);
    }

    public function testStageResizesToJpegAndAdoptMoves(): void
    {
        Storage::fake('local');
        $id = PhotoService::stage($this->png(2000, 1000), '1.1.1.1');
        $draft = PhotoService::draftPath($id);
        $this->assertNotNull($draft);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('local')->get($draft));
        $this->assertSame(800, $w);
        $this->assertSame(400, $h);
        $this->assertSame(IMAGETYPE_JPEG, $type);

        $final = PhotoService::adopt($draft, 'uuid-1');
        $this->assertTrue(Storage::disk('local')->exists($final));
        $this->assertFalse(Storage::disk('local')->exists($draft));
    }

    public function testRejectsNonImageAndBadIds(): void
    {
        Storage::fake('local');
        $f = tempnam(sys_get_temp_dir(), 'tx');
        file_put_contents($f, '<?php echo 1;');
        try {
            PhotoService::stage(new UploadedFile($f, 'x.png', 'image/png', null, true), '2.2.2.2');
            $this->fail('Debió rechazar un archivo que no es imagen');
        } catch (ApplicationException $e) {
            $this->assertStringContainsString('JPG', $e->getMessage());
        }
        $this->assertNull(PhotoService::draftPath('../../etc/passwd'));
        $this->assertNull(PhotoService::draftPath(str_repeat('a', 20)));
    }
}
