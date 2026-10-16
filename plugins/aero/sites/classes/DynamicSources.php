<?php namespace Aero\Sites\Classes;

use Cms\Classes\Controller;

/**
 * Registro de contenido dinámico para el bloque «Contenido dinámico» de Puck.
 *
 * El editor guarda un marcador fijo en el HTML de la página:
 *   <div data-aero-dynamic="carta">…</div>
 * Al mostrar la página, DynamicSources::render() reemplaza cada marcador por
 * el partial vivo de su fuente (con los datos actuales del tenant). Así el
 * contenido nunca queda congelado al momento de publicar.
 *
 * Para agregar una fuente: una entrada en registry() y, si hace falta, un
 * método handler que devuelva el HTML. Si el plugin requerido no está
 * instalado, la fuente no se ofrece en el editor y el marcador se quita.
 */
class DynamicSources
{
    public static function registry(): array
    {
        return [
            'carta' => [
                'label'    => 'Carta (restaurante)',
                'plugin'   => \Aero\Shop\Plugin::class,
                'handler'  => [self::class, 'renderCarta'],
                // Cada fuente tiene exactamente 3 variantes numeradas; el marcador guarda el número.
                // Qué significa cada número lo decide el handler de la fuente (ver renderCarta).
                'variantes' => ['1' => 'Variante 1', '2' => 'Variante 2', '3' => 'Variante 3'],
            ],
            'catalogo' => [
                'label'    => 'Catálogo de productos',
                'plugin'   => \Aero\Shop\Plugin::class,
                'handler'  => [self::class, 'renderCatalogo'],
                'variantes' => ['1' => 'Variante 1', '2' => 'Variante 2', '3' => 'Variante 3'],
            ],
            'horario' => [
                'label'    => 'Horario de atención',
                'plugin'   => \Aero\Sites\Plugin::class,
                'handler'  => [self::class, 'renderHorario'],
                // 1 = agrupado (por defecto), 2 = lista por día, 3 = compacto con estado «abierto ahora».
                'variantes' => ['1' => 'Agrupado por días', '2' => 'Un día por línea', '3' => 'Compacto con estado'],
            ],
            // Solo render: lo ofrece el bloque propio «Servicios de la plataforma»
            // (PlatformServicesBlock), no el selector de «Contenido dinámico».
            'servicios' => [
                'label'    => 'Servicios de la plataforma',
                'plugin'   => \Aero\Services\Plugin::class,
                'handler'  => [PlatformServicesBlock::class, 'render'],
                'editor'   => false,
                'variantes' => [],
            ],
        ];
    }

    /** Opciones del selector del editor: solo fuentes con su plugin instalado. */
    public static function forEditor(): array
    {
        $options = [];
        foreach (self::registry() as $key => $def) {
            if (($def['editor'] ?? true) && class_exists($def['plugin'])) {
                $variants = [];
                foreach ($def['variantes'] as $value => $label) {
                    $variants[] = ['label' => $label, 'value' => (string) $value];
                }
                $options[] = ['label' => $def['label'], 'value' => $key, 'variantes' => $variants];
            }
        }
        return $options;
    }

    /** Reemplaza los marcadores del HTML por el contenido vivo de cada fuente. */
    public static function render(string $html): string
    {
        return preg_replace_callback(
            '/<div\b[^>]*\bdata-aero-dynamic="([a-z0-9_-]*)"[^>]*>.*?<\/div>/s',
            function ($match) {
                // Sin fuente elegida (o fuente que ya no existe): el marcador se quita.
                $def = self::registry()[$match[1]] ?? null;
                if (!$def || !class_exists($def['plugin'])) {
                    return '';
                }
                // La variante es opcional: si falta o no existe, cada fuente usa su presentación por defecto.
                preg_match('/\bdata-variant="([a-z0-9_-]*)"/', $match[0], $variant);
                // Los handlers que lo necesitan reciben además todos los data-* del marcador.
                $attrs = [];
                if (preg_match_all('/\bdata-([a-z-]+)="([^"]*)"/', strtok($match[0], '>'), $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $pair) {
                        $attrs[$pair[1]] = html_entity_decode($pair[2], ENT_QUOTES);
                    }
                }
                if (($def['editor'] ?? true) === false) {
                    return (string) call_user_func($def['handler'], $attrs);
                }
                return (string) call_user_func($def['handler'], $variant[1] ?? '');
            },
            $html
        ) ?? $html;
    }

    /**
     * Reutiliza el partial de la carta del componente shopCatalog. Ese partial
     * depende del estado del componente (menú, moneda, mesa), así que se
     * ejecuta el ciclo del componente dentro de la página actual.
     */
    protected static function renderCarta(string $variante = ''): string
    {
        $controller = Controller::getController();
        if (!$controller) {
            return '';
        }

        $component = $controller->addComponent('shopCatalog', 'shopCatalog');
        if (!$component) {
            return '';
        }

        $component->onRun();
        if (!$component->restaurantStore) {
            return '';
        }

        // Carta: variante 1 = lista (por defecto), 2 = tarjetas, 3 = compacta.
        $presentacion = ['2' => 'tarjetas', '3' => 'compacta'][$variante] ?? 'lista';

        return $controller->renderPartial('shopCatalog::restaurant', ['presentacion' => $presentacion]);
    }

    /**
     * Catálogo de la tienda embebido en la página: misma vista que /tienda, pero
     * sin colecciones ni paginación y con un número limitado de productos.
     * Solo aplica a tiendas en modo tienda (en modo restaurante se muestra la carta).
     */
    protected static function renderCatalogo(string $variante = ''): string
    {
        $controller = Controller::getController();
        if (!$controller) {
            return '';
        }

        $component = $controller->addComponent('shopCatalog', 'shopCatalog');
        if (!$component) {
            return '';
        }

        $component->onRun();
        if ($component->restaurantStore) {
            return '';
        }

        // Variante 1 = cuadrícula (por defecto), 2 = lista, 3 = compacta.
        $presentacion = ['2' => 'lista', '3' => 'compacta'][$variante] ?? 'cuadricula';

        return $controller->renderPartial('shopCatalog::default', [
            'presentacion' => $presentacion,
            'embebido'     => true,
            'limite'       => 6,
        ]);
    }

    /**
     * Horario de atención del tenant del sitio, tal como lo carga en
     * Configuración del sitio → Horarios. Sin horario cargado no muestra nada.
     */
    protected static function renderHorario(string $variante = ''): string
    {
        $tenant = \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost());
        $config = $tenant ? \Aero\Sites\Models\ContactConfig::where('tenant_id', $tenant->id)->first() : null;
        $list   = (array) ($config->business_hours ?? []);

        if (!BusinessHours::hasAny($list)) {
            return '';
        }

        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rows = $variante === '2'
            ? array_map(fn ($day, $shifts) => ['label' => BusinessHours::DAYS[$day], 'value' => BusinessHours::shiftsText($shifts), 'closed' => !$shifts],
                array_keys(BusinessHours::byDay($list)), BusinessHours::byDay($list))
            : BusinessHours::grouped($list);

        $badge = '';
        if ($variante === '3') {
            $open  = BusinessHours::isOpenNow($list, 'America/La_Paz');
            $badge = '<span style="display:inline-block;padding:.15rem .7rem;border-radius:9999px;font-size:.8rem;font-weight:600;margin-left:.75rem;vertical-align:middle;'
                . ($open ? 'background:#dcfce7;color:#166534' : 'background:#fee2e2;color:#991b1b') . '">'
                . ($open ? 'Abierto ahora' : 'Cerrado ahora') . '</span>';
        }

        $html = '<section class="reveal py-16 px-4"><div class="mx-auto max-w-2xl">'
            . '<h2 class="font-heading2 text-3xl font-bold text-ink mb-6 text-center">Horario de atención' . $badge . '</h2>'
            . '<div class="rounded-brand border border-surface-border bg-surface-alt" style="padding:.25rem 1.25rem">';

        foreach ($rows as $i => $row) {
            $html .= '<div style="display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0;'
                . ($i ? 'border-top:1px solid var(--color-surface-border, rgba(128,128,128,.25));' : '') . '">'
                . '<span class="font-medium text-ink">' . $e($row['label']) . '</span>'
                . '<span class="' . ($row['closed'] ? 'text-ink-muted' : 'text-ink') . '">' . $e($row['value']) . '</span></div>';
        }

        return $html . '</div></div></section>';
    }
}
