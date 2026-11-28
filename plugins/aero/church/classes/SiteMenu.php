<?php namespace Aero\Church\Classes;

use Aero\Church\Models\ChurchSettings;

/** Enlaces del menú del sitio: solo las secciones que la iglesia tiene activas (las demás no existen para el visitante). */
class SiteMenu
{
    /** Interruptor de la configuración => [url, etiqueta, va en la barra (true) o dentro de «Más»]. */
    protected const ITEMS = [
        ['site_enabled',    '/eventos',       'Eventos',       true],
        ['site_enabled',    '/predicaciones', 'Predicaciones', true],
        ['prayer_enabled',  '/oracion',       'Oración',       true],
        ['welcome_enabled', '/bienvenida',    'Soy nuevo',     true],
        ['site_enabled',    '/en-vivo',       'En vivo',       false],
        ['site_enabled',    '/noticias',      'Noticias',      false],
        ['site_enabled',    '/recursos',      'Recursos',      false],
        ['portal_enabled',  '/miembro',       'Mi portal',     false],
    ];

    /** @return array{main: array, more: array} cada enlace es ['url' => …, 'label' => …] */
    public static function forTenant(?int $tenantId): array
    {
        $menu = ['main' => [], 'more' => []];
        $settings = $tenantId ? ChurchSettings::where('tenant_id', $tenantId)->first() : null;
        if (!$settings) {
            return $menu;
        }
        if ($settings->site_enabled && WeeklySchedule::exists($tenantId)) {
            $menu['main'][] = ['url' => '/horarios', 'label' => 'Horarios'];
        }
        foreach (self::ITEMS as [$flag, $url, $label, $main]) {
            if ($settings->{$flag}) {
                $menu[$main ? 'main' : 'more'][] = ['url' => $url, 'label' => $label];
            }
        }

        return $menu;
    }
}
