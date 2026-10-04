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
        ];
    }

    /** Opciones del selector del editor: solo fuentes con su plugin instalado. */
    public static function forEditor(): array
    {
        $options = [];
        foreach (self::registry() as $key => $def) {
            if (class_exists($def['plugin'])) {
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
}
