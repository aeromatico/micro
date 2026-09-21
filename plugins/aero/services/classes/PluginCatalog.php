<?php namespace Aero\Services\Classes;

use System\Classes\PluginManager;

/** Plugins instalados y tipos de relación posibles entre un servicio y un plugin. */
class PluginCatalog
{
    public const RELATIONS = [
        'requires'    => 'Requiere el plugin',
        'includes'    => 'Incluye / configura el plugin',
        'integrates'  => 'Se integra con el plugin',
        'recommended' => 'Recomendado para el plugin',
    ];

    /** [código => nombre] p. ej. 'Aero.Shop' => 'Shop'. */
    public static function pluginOptions(): array
    {
        $options = [];

        foreach (PluginManager::instance()->getPlugins() as $code => $plugin) {
            $name = (string) ($plugin->pluginDetails()['name'] ?? $code);
            $options[$code] = $code . ' — ' . trans($name);
        }

        asort($options);

        return $options;
    }

    public static function relationOptions(): array
    {
        return self::RELATIONS;
    }
}
