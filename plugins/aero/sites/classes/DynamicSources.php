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
                'label'   => 'Carta (restaurante)',
                'plugin'  => \Aero\Shop\Plugin::class,
                'handler' => [self::class, 'renderCarta'],
            ],
        ];
    }

    /** Opciones del selector del editor: solo fuentes con su plugin instalado. */
    public static function forEditor(): array
    {
        $options = [];
        foreach (self::registry() as $key => $def) {
            if (class_exists($def['plugin'])) {
                $options[] = ['label' => $def['label'], 'value' => $key];
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
                return (string) call_user_func($def['handler']);
            },
            $html
        ) ?? $html;
    }

    /**
     * Reutiliza el partial de la carta del componente shopCatalog. Ese partial
     * depende del estado del componente (menú, moneda, mesa), así que se
     * ejecuta el ciclo del componente dentro de la página actual.
     */
    protected static function renderCarta(): string
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

        return $controller->renderPartial('shopCatalog::restaurant');
    }
}
