<?php namespace Aero\Sites\ReportWidgets;

use BackendMenu;
use Backend\Classes\ReportWidgetBase;

/**
 * "Acceso rápido" (speed dial): atajos a las áreas del menú principal y sus
 * subsecciones. Se arma desde el propio menú del backend, así que ya viene
 * filtrado por los permisos del usuario (y por el tenant, vía los listeners
 * de menú) y se mantiene solo cuando un plugin agrega pantallas.
 */
class SpeedDial extends ReportWidgetBase
{
    protected $defaultAlias = 'aero_sites_speed_dial';

    public function render()
    {
        $pinned = $this->property('pinned', []);
        $pinned = is_array($pinned) ? array_values(array_filter($pinned, 'is_string')) : [];
        $showSub = filter_var($this->property('subitems', true), FILTER_VALIDATE_BOOLEAN);

        $groups = [];
        foreach (BackendMenu::listMainMenuItemsWithSubitems() as $info) {
            $main = $info->mainMenuItem;

            if ($main->owner === 'October.Dashboard') {
                continue;
            }

            $key = static::menuKey($main->owner, $main->code);
            if ($pinned && !in_array($key, $pinned, true)) {
                continue;
            }

            $subs = [];
            if ($showSub) {
                foreach ($info->subMenuItems as $sub) {
                    if (!$sub->url) {
                        continue;
                    }
                    $subs[] = [
                        'label' => __($sub->label),
                        'icon'  => $sub->icon,
                        'svg'   => $sub->iconSvg,
                        'url'   => $sub->url,
                    ];
                }
            }

            $groups[] = [
                'label' => __($main->label),
                'icon'  => $main->icon,
                'svg'   => $main->iconSvg,
                'url'   => $main->url,
                'subs'  => $subs,
            ];
        }

        $this->vars['groups'] = $groups;
        $this->vars['widgetId'] = 'speeddial-' . $this->alias;

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'title' => [
                'title'   => 'Título',
                'default' => 'Acceso rápido',
                'type'    => 'string',
            ],
            'subitems' => [
                'title'   => 'Mostrar subsecciones',
                'default' => true,
                'type'    => 'checkbox',
            ],
            'pinned' => [
                'title'       => 'Áreas a mostrar',
                'description' => 'Vacío = todas las áreas a las que tienes acceso.',
                'type'        => 'set',
                'default'     => [],
            ],
        ];
    }

    /**
     * Clave de un área sin puntos: el Inspector trata los puntos como rutas
     * anidadas y rompería el valor marcado ("Aero.Sites" → {Aero:{Sites…}}).
     */
    protected static function menuKey(string $owner, string $code): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $owner . '__' . $code));
    }

    public function getPinnedOptions(): array
    {
        $options = [];
        foreach (BackendMenu::listMainMenuItems() as $item) {
            if ($item->owner === 'October.Dashboard') {
                continue;
            }
            $options[static::menuKey($item->owner, $item->code)] = __($item->label);
        }

        return $options;
    }
}
