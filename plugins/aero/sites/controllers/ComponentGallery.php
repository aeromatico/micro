<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\ComponentBlockCatalog;
use Aero\Sites\Models\DesignTheme;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use Response;

/**
 * Galería de referencia (antes interna/solo-superadmin) para diseñar y
 * previsualizar variantes de layout real de los bloques Puck, lado a lado,
 * con el CSS/tipografía/paleta reales del tema `microsites` — no una maqueta
 * separada. `preview()` sirve el mismo par components.jsx/PuckHtmlRenderer.php
 * que usan el editor visual y el generador con IA, así que cualquier
 * variante que se vea aquí es automáticamente una opción real y sincronizada
 * en ambos. Abierta también a tenant admins (manage_pages) — les sirve como
 * inspiración/referencia mientras diseñan, junto a Contenidos.
 *
 * El catálogo de bloques/variantes/props y la lógica de render standalone
 * viven en Aero\Sites\Classes\ComponentBlockCatalog — compartido con la
 * galería pública del theme `master` (/elements/blocks, ver
 * Components\BlocksGallery y Components\BlocksPreview) para que ambas vistas
 * no puedan desincronizarse.
 */
class ComponentGallery extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.sites.superadmin', 'aero.sites.manage_pages'];

    public function __construct()
    {
        parent::__construct();
        $this->setSitesMenuContext('componentgallery', 'componentgallery');
    }

    public function index()
    {
        $this->pageTitle = 'Galería de componentes';

        $this->vars['blocks'] = ComponentBlockCatalog::BLOCKS;
        $this->vars['themes'] = DesignTheme::active()->orderBy('name')->get(['id', 'handle', 'name']);
        $this->vars['defaultThemeHandle'] = ComponentBlockCatalog::resolveDefaultThemeHandle($this->vars['themes']);
    }

    /**
     * Devuelve HTML crudo standalone (no AJAX, GET normal) para usar como
     * `src` de un <iframe>: un único bloque renderizado con
     * HeadlessRenderer + el CSS compilado real de themes/microsites y las
     * CSS vars del DesignTheme elegido. Sigue protegida por
     * requiredPermissions igual que el resto del controlador — no es un
     * endpoint público (para eso existe Components\BlocksPreview, que llama
     * al mismo ComponentBlockCatalog::renderPreviewDocument()).
     */
    public function preview()
    {
        $block = (string) (input('block') ?: 'Hero');
        $variant = (string) (input('variant') ?: 'centrado');
        $themeHandle = input('theme');
        $mode = input('mode') === 'dark' ? 'dark' : 'light';

        $propsOverride = null;
        $rawOverride = input('props');
        if (is_string($rawOverride) && $rawOverride !== '') {
            $decoded = json_decode($rawOverride, true);
            if (is_array($decoded)) {
                $propsOverride = $decoded;
            }
        }

        $document = ComponentBlockCatalog::renderPreviewDocument($block, $variant, $themeHandle, $mode, $propsOverride);

        return Response::make($document, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
