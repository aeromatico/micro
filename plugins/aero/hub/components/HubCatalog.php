<?php namespace Aero\Hub\Components;

use Aero\Hub\Classes\CatalogSync;
use Cms\Classes\ComponentBase;

/**
 * Alimenta la página pública /hub y la sección destacada de /documentacion:
 * catálogo activo (agrupado Modelos IA / APIs → categoría) y sus conteos,
 * armados por `CatalogSync::publicCatalog()`/`publicStats()` — mismo patrón
 * que `Aero\Api\Components\ApiExplorer` para el explorador de API.
 */
class HubCatalog extends ComponentBase
{
    public array $catalog = [];

    /** Volcado crudo a un `<script type="application/json">` para Alpine, igual que ApiExplorer. */
    public string $catalogJson = '[]';

    public array $stats = [];

    public function componentDetails(): array
    {
        return [
            'name'        => 'Aero Hub — catálogo',
            'description' => 'Catálogo activo de Aero Hub (Modelos IA + APIs) para la página pública y la sección destacada de documentación.',
        ];
    }

    public function onRun()
    {
        $this->catalog = CatalogSync::publicCatalog();
        $this->catalogJson = str_replace('</', '<\/', json_encode($this->catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->stats = CatalogSync::publicStats();
    }
}
