<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\Ai\HeadlessRenderer;
use Aero\Sites\Classes\Ai\ImageSourceService;
use Aero\Sites\Models\DesignTheme;
use Backend\Classes\Controller;
use BackendMenu;
use Response;

/**
 * Herramienta interna (no pública) para diseñar y previsualizar variantes de
 * layout real de los bloques Puck, lado a lado, con el CSS/tipografía/paleta
 * reales del tema `microsites` — no una maqueta separada. `preview()` sirve
 * el mismo par components.jsx/PuckHtmlRenderer.php que usan el editor visual
 * y el generador con IA, así que cualquier variante que se vea acá es
 * automáticamente una opción real y sincronizada en ambos.
 */
class ComponentGallery extends Controller
{
    public $requiredPermissions = ['aero.sites.superadmin'];

    /**
     * Catálogo de bloques con galería de variantes real. El resto de
     * components.jsx queda como "próximamente" hasta que se les apliquen
     * variantes de layout siguiendo el mismo patrón (ver plan de la sesión).
     */
    public const HERO_VARIANTS = [
        'centrado'         => 'Centrado (clásico)',
        'imagen-derecha'   => 'Imagen a la derecha',
        'imagen-izquierda' => 'Imagen a la izquierda',
        'fondo-completo'   => 'Fondo completo (alto impacto)',
        'minimal'          => 'Minimal (solo texto)',
    ];

    /**
     * Espejo del defaultProps de Hero en components.jsx — no hay codegen
     * compartido, mantener sincronizado a mano igual que renderHero().
     */
    public const HERO_DEFAULT_PROPS = [
        'title'       => 'Bienvenido a nuestro sitio',
        'subtitle'    => 'Descubre todo lo que tenemos para ofrecerte.',
        'description' => 'Contamos con años de experiencia ayudando a negocios como el tuyo a crecer y destacarse.',
        'ctaLabel'    => 'Contáctanos',
        'ctaUrl'      => '/contacto',
        'cta2Label'   => 'Conocer más',
        'cta2Url'     => '/nosotros',
        'bgImage'     => '',
        'image'       => '',
        'variant'     => 'centrado',
        'background'  => 'surface',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const FEATURE_GRID_VARIANTS = [
        'tarjetas'        => 'Tarjetas (clásico)',
        'lista'           => 'Lista vertical',
        'numeradas'       => 'Pasos numerados',
        'imagen-lateral'  => 'Imagen + lista al lado',
        'destacado'       => 'Encabezado destacado + íconos',
    ];

    /**
     * Espejo del defaultProps de FeatureGrid en components.jsx — igual
     * criterio que HERO_DEFAULT_PROPS, mantener sincronizado a mano junto a
     * renderFeatureGrid().
     */
    public const FEATURE_GRID_DEFAULT_PROPS = [
        'title'       => 'Todo lo que necesitás',
        'subtitle'    => 'Pensado para que empieces a ver resultados desde el primer día.',
        'description' => '',
        'ctaLabel'    => '',
        'ctaUrl'      => '',
        'image'       => '',
        'features'    => [
            ['icon' => 'tabler:star', 'title' => 'Característica 1', 'description' => 'Descripción del primer beneficio.'],
            ['icon' => 'tabler:rocket', 'title' => 'Característica 2', 'description' => 'Descripción del segundo beneficio.'],
            ['icon' => 'tabler:bulb', 'title' => 'Característica 3', 'description' => 'Descripción del tercer beneficio.'],
        ],
        'columns'     => '3',
        'variant'     => 'tarjetas',
        'background'  => '',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const CTA_VARIANTS = [
        'clasico'         => 'Clásico (un botón)',
        'doble-boton'     => 'Doble botón',
        'con-icono'       => 'Con ícono',
        'imagen-lateral'  => 'Imagen al lado',
        'franja-minimal'  => 'Franja minimal',
    ];

    /**
     * Espejo del defaultProps de CTASection en components.jsx — igual
     * criterio que HERO_DEFAULT_PROPS, mantener sincronizado a mano junto a
     * renderCTASection().
     */
    public const CTA_DEFAULT_PROPS = [
        'heading'    => '¿Listo para comenzar?',
        'subtitle'   => 'Sumate a los negocios que ya están creciendo con nosotros.',
        'body'       => 'Contáctanos hoy y descubre cómo podemos ayudarte.',
        'buttonLabel' => 'Comenzar ahora',
        'buttonUrl'   => '/contacto',
        'cta2Label'   => 'Ver planes',
        'cta2Url'     => '/planes',
        'icon'        => 'tabler:rocket',
        'image'       => '',
        'variant'     => 'clasico',
        'style'       => 'solid',
        'background'  => '',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const PRICING_VARIANTS = [
        'tres-planes'            => '3 planes — tarjetas (clásico)',
        'tres-planes-contraste'  => '3 planes — contraste destacado',
        'tres-planes-tabla'      => '3 planes — tabla minimal',
        'dos-planes'             => '2 planes',
        'un-plan'                => '1 plan (producto/servicio único)',
    ];

    /**
     * Espejo del defaultProps de Pricing en components.jsx — igual criterio
     * que HERO_DEFAULT_PROPS, mantener sincronizado a mano junto a
     * renderPricing().
     */
    public const PRICING_DEFAULT_PROPS = [
        'title'       => 'Planes y precios',
        'subtitle'    => 'Elegí el plan que mejor se adapte a tu negocio.',
        'description' => '',
        'plans' => [
            [
                'name' => 'Básico', 'price' => '$19', 'period' => '/mes',
                'description' => 'Para empezar.',
                'features' => "Hasta 1.000 visitas\nSoporte por email\n1 usuario",
                'ctaLabel' => 'Elegir Básico', 'ctaUrl' => '/contacto', 'highlighted' => 'no', 'icon' => 'tabler:star',
            ],
            [
                'name' => 'Pro', 'price' => '$49', 'period' => '/mes',
                'description' => 'El más elegido.',
                'features' => "Visitas ilimitadas\nSoporte prioritario\n5 usuarios\nReportes avanzados",
                'ctaLabel' => 'Elegir Pro', 'ctaUrl' => '/contacto', 'highlighted' => 'yes', 'icon' => 'tabler:rocket',
            ],
            [
                'name' => 'Premium', 'price' => '$99', 'period' => '/mes',
                'description' => 'Para equipos grandes.',
                'features' => "Todo lo de Pro\nUsuarios ilimitados\nSoporte 24/7\nIntegraciones a medida",
                'ctaLabel' => 'Elegir Premium', 'ctaUrl' => '/contacto', 'highlighted' => 'no', 'icon' => 'tabler:diamond',
            ],
        ],
        'variant'     => 'tres-planes',
        'background'  => '',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const FAQ_VARIANTS = [
        'acordeon-clasico'     => 'Acordeón clásico',
        'acordeon-exclusivo'   => 'Acordeón exclusivo (numerado)',
        'tarjetas-grid'        => 'Tarjetas en grid',
        'conversacional'       => 'Conversacional (chat)',
        'dividido-lateral'     => 'Dividido — panel lateral',
    ];

    /**
     * Espejo del defaultProps de FAQ en components.jsx — mantener
     * sincronizado a mano junto a renderFAQ().
     */
    public const FAQ_DEFAULT_PROPS = [
        'title'       => 'Preguntas Frecuentes',
        'subtitle'    => 'Todo lo que necesitás saber antes de empezar.',
        'description' => '',
        'items' => [
            [
                'icon' => 'tabler:credit-card', 'question' => '¿Cómo funciona el servicio?',
                'answer' => '<p>Te registrás, elegís un plan y en minutos tenés tu sitio publicado.</p>',
                'links' => 'Ver guía de inicio | /guia',
            ],
            [
                'icon' => 'tabler:wallet', 'question' => '¿Cuáles son los precios?',
                'answer' => '<p>Tenemos planes desde $19/mes, sin permanencia mínima.</p>',
                'links' => '',
            ],
            [
                'icon' => 'tabler:lock', 'question' => '¿Mis datos están seguros?',
                'answer' => '<p>Sí, usamos cifrado en tránsito y en reposo, con respaldos diarios.</p>',
                'links' => 'Política de privacidad | /privacidad',
            ],
        ],
        'variant'     => 'acordeon-clasico',
        'background'  => '',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const TABS_VARIANTS = [
        'clasicas'    => 'Clásicas — subrayado',
        'pildoras'    => 'Píldoras',
        'verticales'  => 'Verticales (lateral)',
        'tarjetas'    => 'Tarjetas',
        'numeradas'   => 'Numeradas (pasos)',
    ];

    /**
     * Espejo del defaultProps de Tabs en components.jsx — mantener
     * sincronizado a mano junto a renderTabs().
     */
    public const TABS_DEFAULT_PROPS = [
        'title' => 'Todo en un solo lugar',
        'tabs' => [
            ['icon' => 'tabler:rocket', 'label' => 'Onboarding', 'content' => '<p>Empezá en minutos con nuestra guía paso a paso y soporte incluido.</p>'],
            ['icon' => 'tabler:chart-bar', 'label' => 'Reportes', 'content' => '<p>Métricas claras de visitas, conversiones y rendimiento en tiempo real.</p>'],
            ['icon' => 'tabler:tool', 'label' => 'Integraciones', 'content' => '<p>Conectá tus herramientas favoritas: WhatsApp, email marketing y más.</p>'],
        ],
        'variant'     => 'clasicas',
        'background'  => '',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const GALLERY_VARIANTS = [
        'grid-uniforme'      => 'Grid uniforme (clásico)',
        'masonry'            => 'Masonry (alturas variables)',
        'carrusel'           => 'Carrusel horizontal',
        'lightbox'           => 'Lightbox (click para ampliar)',
        'editorial-alterno'  => 'Editorial (alternada)',
    ];

    /**
     * Espejo del defaultProps de Gallery en components.jsx — mantener
     * sincronizado a mano junto a renderGallery().
     */
    public const GALLERY_DEFAULT_PROPS = [
        'variant' => 'grid-uniforme',
        'title'   => 'Galería',
        'images'  => [
            ['url' => 'https://placehold.co/600x400/e2e8f0/94a3b8?text=1', 'alt' => 'Imagen 1', 'caption' => 'Leyenda de ejemplo'],
            ['url' => 'https://placehold.co/600x400/e2e8f0/94a3b8?text=2', 'alt' => 'Imagen 2', 'caption' => ''],
            ['url' => 'https://placehold.co/600x400/e2e8f0/94a3b8?text=3', 'alt' => 'Imagen 3', 'caption' => ''],
            ['url' => 'https://placehold.co/600x400/e2e8f0/94a3b8?text=4', 'alt' => 'Imagen 4', 'caption' => ''],
            ['url' => 'https://placehold.co/600x400/e2e8f0/94a3b8?text=5', 'alt' => 'Imagen 5', 'caption' => 'Otra leyenda'],
            ['url' => 'https://placehold.co/600x400/e2e8f0/94a3b8?text=6', 'alt' => 'Imagen 6', 'caption' => ''],
        ],
        'background'      => '',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const STATS_VARIANTS = [
        'tres-columnas'       => '3 columnas (clásico)',
        'con-iconos'          => 'Con íconos',
        'franja-destacada'    => 'Franja destacada',
        'contador-destacado'  => 'Contador destacado',
        'tarjetas-elevadas'   => 'Tarjetas elevadas',
    ];

    /**
     * Espejo del defaultProps de Stats en components.jsx — mantener
     * sincronizado a mano junto a renderStats().
     */
    public const STATS_DEFAULT_PROPS = [
        'variant' => 'tres-columnas',
        'title'   => 'Nuestros números',
        'stats' => [
            ['icon' => 'tabler:users', 'value' => '+500', 'label' => 'Clientes', 'description' => 'En toda la región'],
            ['icon' => 'tabler:calendar', 'value' => '10', 'label' => 'Años de experiencia', 'description' => ''],
            ['icon' => 'tabler:headset', 'value' => '24/7', 'label' => 'Soporte', 'description' => 'Siempre disponibles'],
        ],
        'background'      => 'surface',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    /**
     * Catálogo de bloques con galería — una entrada acá alcanza para que
     * aparezcan en el selector de la vista y en preview()/autoResolveImages().
     * `columns` controla el grid de tarjetas de la propia galería (no el
     * `columns` interno de FeatureGrid, que es una prop del bloque) y
     * `previewHeight` el alto del iframe de cada tarjeta.
     */
    public const BLOCKS = [
        'Hero' => [
            'label'         => 'Hero',
            'variants'      => self::HERO_VARIANTS,
            'defaultProps'  => self::HERO_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 520,
        ],
        'FeatureGrid' => [
            'label'         => 'Características',
            'variants'      => self::FEATURE_GRID_VARIANTS,
            'defaultProps'  => self::FEATURE_GRID_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 640,
        ],
        'CTASection' => [
            'label'         => 'Llamado a la acción',
            'variants'      => self::CTA_VARIANTS,
            'defaultProps'  => self::CTA_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 420,
        ],
        'Pricing' => [
            'label'         => 'Planes y precios',
            'variants'      => self::PRICING_VARIANTS,
            'defaultProps'  => self::PRICING_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 780,
        ],
        'FAQ' => [
            'label'         => 'Preguntas frecuentes',
            'variants'      => self::FAQ_VARIANTS,
            'defaultProps'  => self::FAQ_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 620,
        ],
        'Tabs' => [
            'label'         => 'Pestañas',
            'variants'      => self::TABS_VARIANTS,
            'defaultProps'  => self::TABS_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 520,
        ],
        'Gallery' => [
            'label'         => 'Galería',
            'variants'      => self::GALLERY_VARIANTS,
            'defaultProps'  => self::GALLERY_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 560,
        ],
        'Stats' => [
            'label'         => 'Estadísticas',
            'variants'      => self::STATS_VARIANTS,
            'defaultProps'  => self::STATS_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 420,
        ],
    ];

    /**
     * defaultProps por bloque, usados por preview(). Agregar bloques nuevos
     * en self::BLOCKS, no acá.
     */
    protected static function defaultPropsFor(string $block): array
    {
        return self::BLOCKS[$block]['defaultProps'] ?? self::HERO_DEFAULT_PROPS;
    }

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'componentgallery');
    }

    public function index()
    {
        $this->pageTitle = 'Galería de componentes';

        $this->vars['blocks'] = self::BLOCKS;
        $this->vars['themes'] = DesignTheme::active()->orderBy('name')->get(['id', 'handle', 'name']);
        $this->vars['defaultThemeHandle'] = optional($this->vars['themes']->first())->handle;
    }

    /**
     * Devuelve HTML crudo standalone (no AJAX, GET normal) para usar como
     * `src` de un <iframe>: un único bloque renderizado con
     * HeadlessRenderer + el CSS compilado real de themes/microsites y las
     * CSS vars del DesignTheme elegido. Sigue protegida por
     * requiredPermissions igual que el resto del controlador — no es un
     * endpoint público.
     */
    public function preview()
    {
        $block = (string) (input('block') ?: 'Hero');
        $variant = (string) (input('variant') ?: 'centrado');
        $themeHandle = input('theme');
        $mode = input('mode') === 'dark' ? 'dark' : 'light';

        $props = self::defaultPropsFor($block);
        $props['variant'] = $variant;

        $propsOverride = input('props');
        if (is_string($propsOverride) && $propsOverride !== '') {
            $decoded = json_decode($propsOverride, true);
            if (is_array($decoded)) {
                $props = array_merge($props, $decoded);
            }
        }

        $props = $this->autoResolveImages($block, $variant, $props);

        $theme = $themeHandle
            ? DesignTheme::where('handle', $themeHandle)->first()
            : DesignTheme::active()->orderBy('name')->first();

        $html = (new HeadlessRenderer())->render([
            'content' => [['type' => $block, 'props' => $props]],
        ]) ?? '';

        $cssVars = $theme ? $theme->toCssVars() : ['light' => [], 'dark' => []];
        $fontsUrl = $this->googleFontsUrlForTheme($theme);

        $document = $this->buildStandaloneDocument($html, $cssVars, $fontsUrl, $mode);

        return Response::make($document, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Resuelve fotos reales vía la API de Unsplash (mismo servicio que usa
     * el generador con IA, con su mismo cache/fallback) para las variantes
     * que necesitan imagen y no traen una ya puesta — así la galería se ve
     * con la experiencia real, no con huecos vacíos. `props` puede pisar
     * esto: si ya viene `image`/`bgImage` (ej. edición en vivo), se respeta.
     */
    protected function autoResolveImages(string $block, string $variant, array $props): array
    {
        if ($block === 'FeatureGrid' || $block === 'CTASection') {
            if ($variant === 'imagen-lateral' && empty($props['image'])) {
                $props['image'] = (new ImageSourceService())->resolve('modern business team office')['url'];
            }
            return $props;
        }

        if ($block !== 'Hero') {
            return $props;
        }

        $images = new ImageSourceService();

        if (in_array($variant, ['imagen-derecha', 'imagen-izquierda'], true) && empty($props['image'])) {
            $props['image'] = $images->resolve('modern business team office')['url'];
        }

        if ($variant === 'fondo-completo' && empty($props['bgImage'])) {
            $props['bgImage'] = $images->resolve('modern business interior architecture')['url'];
        }

        return $props;
    }

    /**
     * Mismo cálculo que Tenant::getGoogleFontsUrl(), pero a partir de un
     * DesignTheme suelto (sin depender de un tenant real) — la galería
     * previsualiza temas, no tenants.
     */
    protected function googleFontsUrlForTheme(?DesignTheme $theme): string
    {
        $vars = $theme ? ($theme->toCssVars()['light'] ?? []) : [];
        $heading  = $vars['--font-heading'] ?? 'Inter';
        $heading2 = $vars['--font-heading-2'] ?? $heading;
        $body     = $vars['--font-body'] ?? 'Inter';

        $families = array_unique([$heading, $heading2, $body]);
        $params = array_map(function ($font) {
            return 'family=' . str_replace(' ', '+', $font) . ':wght@400;500;600;700;800';
        }, $families);

        return 'https://fonts.googleapis.com/css2?' . implode('&', $params) . '&display=swap';
    }

    protected function buildStandaloneDocument(string $bodyHtml, array $cssVars, string $fontsUrl, string $mode): string
    {
        $cssPath = themes_path('microsites/assets/css/app.min.css');
        $cssVersion = file_exists($cssPath) ? hash('crc32', (string) filemtime($cssPath)) : '1';
        $cssUrl = url('themes/microsites/assets/css/app.min.css') . '?v=' . $cssVersion;
        $htmlClass = $mode === 'dark' ? ' class="dark"' : '';

        $varsToCss = function (array $vars): string {
            $out = '';
            foreach ($vars as $name => $value) {
                $out .= $name . ':' . $value . ';';
            }
            return $out;
        };

        $lightVars = $varsToCss($cssVars['light'] ?? []);
        $darkVars = $varsToCss($cssVars['dark'] ?? []);

        return <<<HTML
<!DOCTYPE html>
<html lang="es"{$htmlClass}>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="{$fontsUrl}" rel="stylesheet">
<link rel="stylesheet" href="{$cssUrl}">
<style>
:root { {$lightVars} }
:root.dark { {$darkVars} }
body { margin: 0; }
</style>
</head>
<body class="bg-surface text-ink font-body antialiased no-animations">
{$bodyHtml}
</body>
</html>
HTML;
    }
}
