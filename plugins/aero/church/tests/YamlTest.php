<?php namespace Aero\Church\Tests;

use PluginTestCase;
use Symfony\Component\Yaml\Yaml;

/** Un YAML mal formado (p. ej. coma dentro de un mapa en línea) rompe el formulario con un error engañoso. */
class YamlTest extends PluginTestCase
{
    public function testAllYamlFilesParse(): void
    {
        $root = dirname(__DIR__);
        $files = array_merge(glob($root . '/models/*/*.yaml'), glob($root . '/controllers/*/*.yaml'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertIsArray(Yaml::parseFile($file), 'YAML inválido: ' . $file);
        }
    }
}
