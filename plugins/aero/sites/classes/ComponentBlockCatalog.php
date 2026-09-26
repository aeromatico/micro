<?php namespace Aero\Sites\Classes;

use Aero\Sites\Classes\Ai\HeadlessRenderer;
use Aero\Sites\Classes\Ai\ImageSourceService;
use Aero\Sites\Models\DesignTheme;

/**
 * Fuente única del catálogo de bloques Puck (variantes de layout + props por
 * defecto) y de la lógica para renderizarlos standalone con el CSS/tema
 * reales. La usan tres lugares que deben verse siempre iguales sin tocarse
 * entre sí:
 *   - Backend\Controllers\ComponentGallery (galería del backend / pestaña
 *     "Componentes" de ContentEditor)
 *   - Components\BlocksGallery + Components\BlocksPreview (galería pública en
 *     el theme `master`, /elements/blocks)
 *   - El editor visual Puck y el generador con IA, vía el mismo
 *     HeadlessRenderer/PuckHtmlRenderer que invoca renderPreviewDocument().
 *
 * Agregar o editar un bloque/variante se hace UNA sola vez acá.
 */
class ComponentBlockCatalog
{
    public const FOOTER_VARIANTS = [
        'columnas'           => 'Columnas (clásico)',
        'minimalista'        => 'Minimalista (centrado)',
        'contacto'           => 'Con contacto',
        'barra-doble'        => 'Barra doble',
        'centrado-columnas'  => 'Centrado con columnas',
    ];

    public const FOOTER_DEFAULT_PROPS = [
        'variant' => 'columnas',
        'brand'   => 'Mi Negocio',
        'tagline' => 'Soluciones simples para hacer crecer tu negocio.',
        'columns' => [
            ['title' => 'Empresa', 'links' => "Nosotros | /nosotros\nServicios | /servicios\nBlog | /blog"],
            ['title' => 'Soporte', 'links' => "Preguntas frecuentes | /faq\nContacto | /contacto"],
            ['title' => 'Legal', 'links' => "Términos | /terminos\nPrivacidad | /privacidad"],
        ],
        'contact'   => "hola@minegocio.com\n+591 700 00000\nLa Paz, Bolivia",
        'copyright' => '© 2026 Mi Negocio. Todos los derechos reservados.',
        'background'      => 'surface',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const HEADER_VARIANTS = [
        'clasico'      => 'Clásico (logo + enlaces + botón)',
        'centrado'     => 'Centrado',
        'barra-marca'  => 'Barra de marca (sólida)',
        'dos-niveles'  => 'Dos niveles (aviso arriba)',
        'flotante'     => 'Flotante (píldora)',
    ];

    public const HEADER_DEFAULT_PROPS = [
        'variant'  => 'clasico',
        'brand'    => 'Mi Negocio',
        'logo'     => '',
        'links'    => "Inicio | /\nServicios | /servicios\nNosotros | /nosotros\nContacto | /contacto",
        'ctaLabel' => 'Empezar',
        'ctaUrl'   => '/contacto',
        'topText'  => 'Envíos gratis en compras sobre Bs 200',
        'background'      => 'surface',
        'customBgColor'   => '',
        'textColor'       => 'auto',
        'customTextColor' => '',
    ];

    public const HERO_VARIANTS = [
        'centrado'         => 'Centrado (clásico)',
        'imagen-derecha'   => 'Imagen a la derecha',
        'imagen-izquierda' => 'Imagen a la izquierda',
        'fondo-completo'   => 'Fondo completo (alto impacto)',
        'minimal'          => 'Minimal (solo texto)',
    ];

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

    public const FAQ_DEFAULT_PROPS = [
        'title'       => 'Preguntas Frecuentes',
        'subtitle'    => 'Todo lo que necesitás saber antes de empezar.',
        'description' => '',
        'items' => [
            [
                'icon' => 'tabler:credit-card', 'question' => '¿Cómo funciona el servicio?',
                'answer' => '<p>Te registras, eliges un plan y en minutos tienes tu sitio publicado.</p>',
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
     * Catálogo de bloques con galería de variantes real. Una entrada acá
     * alcanza para que aparezcan en el selector de las tres vistas (backend,
     * galería pública del theme `master` y preview()). `columns` controla el
     * grid de tarjetas de la galería (no el `columns` interno de FeatureGrid,
     * que es una prop del bloque) y `previewHeight` el alto del iframe de
     * cada tarjeta.
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
        'Header' => [
            'label'         => 'Header (Navbar)',
            'variants'      => self::HEADER_VARIANTS,
            'defaultProps'  => self::HEADER_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 260,
        ],
        'Footer' => [
            'label'         => 'Footer',
            'variants'      => self::FOOTER_VARIANTS,
            'defaultProps'  => self::FOOTER_DEFAULT_PROPS,
            'columns'       => 1,
            'previewHeight' => 380,
        ],
    ];

    /**
     * Tema preseleccionado en el selector de la galería — el resto queda
     * disponible en el dropdown, este es solo el que se ve al entrar.
     */
    public const DEFAULT_THEME_HANDLE = 'corporate-indigo';

    public static function defaultPropsFor(string $block): array
    {
        return self::BLOCKS[$block]['defaultProps'] ?? self::HERO_DEFAULT_PROPS;
    }

    public static function resolveDefaultThemeHandle($themes): ?string
    {
        if ($themes->contains('handle', self::DEFAULT_THEME_HANDLE)) {
            return self::DEFAULT_THEME_HANDLE;
        }

        return optional($themes->first())->handle;
    }

    /**
     * Resuelve fotos reales vía la API de Unsplash (mismo servicio que usa
     * el generador con IA, con su mismo cache/fallback) para las variantes
     * que necesitan imagen y no traen una ya puesta — así la galería se ve
     * con la experiencia real, no con huecos vacíos. `props` puede pisar
     * esto: si ya viene `image`/`bgImage` (ej. edición en vivo), se respeta.
     */
    public static function autoResolveImages(string $block, string $variant, array $props): array
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
    public static function googleFontsUrlForTheme(?DesignTheme $theme): string
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

    public static function buildStandaloneDocument(string $bodyHtml, array $cssVars, string $fontsUrl, string $mode): string
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

    /**
     * Punto de entrada único usado por ComponentGallery::preview() (backend)
     * y Components\BlocksPreview (galería pública del theme `master`) —
     * mismo HTML devuelto en ambos casos para el mismo (block, variant,
     * theme, mode, props), así las dos vistas no pueden desincronizarse.
     */
    public static function renderPreviewDocument(
        string $block,
        string $variant,
        ?string $themeHandle,
        string $mode,
        ?array $propsOverride = null
    ): string {
        $props = self::defaultPropsFor($block);
        $props['variant'] = $variant;

        if ($propsOverride) {
            $props = array_merge($props, $propsOverride);
        }

        $props = self::autoResolveImages($block, $variant, $props);

        $theme = $themeHandle
            ? DesignTheme::where('handle', $themeHandle)->first()
            : DesignTheme::active()->orderBy('name')->first();

        $html = (new HeadlessRenderer())->render([
            'content' => [['type' => $block, 'props' => $props]],
        ]) ?? '';

        $cssVars = $theme ? $theme->toCssVars() : ['light' => [], 'dark' => []];
        $fontsUrl = self::googleFontsUrlForTheme($theme);

        return self::buildStandaloneDocument($html, $cssVars, $fontsUrl, $mode);
    }
}
