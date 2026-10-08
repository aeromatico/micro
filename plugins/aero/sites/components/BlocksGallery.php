<?php namespace Aero\Sites\Components;

use Aero\Sites\Classes\ComponentBlockCatalog;
use Aero\Sites\Models\DesignTheme;
use Cms\Classes\ComponentBase;

/**
 * Alimenta la galería pública de bloques Puck en el theme `master`
 * (/elements/blocks). Lee el mismo catálogo que la galería del backend
 * (pestaña "Componentes" de Contenidos) — Aero\Sites\Classes\ComponentBlockCatalog
 * — así que agregar o editar un bloque ahí lo actualiza automáticamente en
 * ambos lugares, sin tocar esta página.
 */
class BlocksGallery extends ComponentBase
{
    public array $blocks = [];

    /** Volcados crudos para leer con Alpine del lado del cliente. */
    public string $blocksJson = '[]';
    public string $themesJson = '[]';
    public string $defaultThemeJson = 'null';

    public $themes;

    public ?string $defaultTheme = null;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Galería de bloques (Puck)',
            'description' => 'Catálogo público de bloques y variantes de layout, con preview en vivo.',
        ];
    }

    public function onRun()
    {
        $this->blocks = ComponentBlockCatalog::BLOCKS;
        $this->themes = DesignTheme::active()->orderBy('name')->get(['handle', 'name']);
        $this->defaultTheme = ComponentBlockCatalog::resolveDefaultThemeHandle($this->themes);

        $this->blocksJson = str_replace('</', '<\/', json_encode($this->blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->themesJson = str_replace('</', '<\/', json_encode($this->themes->values(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->defaultThemeJson = json_encode($this->defaultTheme);
    }
}
