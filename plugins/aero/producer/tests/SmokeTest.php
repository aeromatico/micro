<?php namespace Aero\Producer\Tests;

use Aero\Producer\Models\Project;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testTableExists(): void
    {
        $this->assertTrue(\Schema::hasTable('aero_producer_projects'));
    }

    public function testSlugIsUniquePerTenant(): void
    {
        Project::create(['tenant_id' => 1, 'slug' => 'demo', 'name' => 'A']);
        $this->assertNotNull(Project::create(['tenant_id' => 2, 'slug' => 'demo', 'name' => 'B'])->id);

        $this->expectException(\October\Rain\Database\ModelException::class);
        Project::create(['tenant_id' => 1, 'slug' => 'demo', 'name' => 'C']);
    }

    public function testMetadataProblemsGuideTheApproval(): void
    {
        $p = Project::create(['tenant_id' => null, 'slug' => 'x', 'name' => 'X']);
        $this->assertNotEmpty($p->metadataProblems());

        $p->metadata = ['title' => str_repeat('a', 101), 'description' => 'd'];
        $p->outputs = [['orientation' => '16:9', 'path' => 'videos/x-v1.mp4']];
        $this->assertSame(['El título supera 100 caracteres.'], $p->metadataProblems());

        $p->metadata = ['title' => 'Ok', 'description' => 'd'];
        $this->assertSame([], $p->metadataProblems());
    }

    public function testImporterCreatesDraftWhenNothingRendered(): void
    {
        $root = sys_get_temp_dir() . '/producer-test-' . uniqid();
        \Aero\Producer\Classes\Importer::$sourceRootOverride = $root;
        $dir = $root . '/zz-prueba-importer';
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/video.json', json_encode(['titulo' => 'Prueba<br>x', 'scenes' => [['text' => 'Hola.']]]));

        try {
            $p = \Aero\Producer\Classes\Importer::import('zz-prueba-importer');
            $this->assertSame('designed', $p->status);
            $this->assertSame('Hola.', $p->script);
        } finally {
            \Aero\Producer\Classes\Importer::$sourceRootOverride = null;
            @unlink($dir . '/video.json');
            @rmdir($dir);
            @rmdir($root);
        }
    }

    public function testImporterRejectsPathLikeSlugs(): void
    {
        $this->assertNull(\Aero\Producer\Classes\Importer::import('../etc'));
        $this->assertNull(\Aero\Producer\Classes\Importer::import('algo-vertical'));
    }
}
