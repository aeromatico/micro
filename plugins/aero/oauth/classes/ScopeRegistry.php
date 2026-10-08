<?php namespace Aero\Oauth\Classes;

use Event;

/**
 * Permisos extra que piden otros plugins (permisos incrementales). El login
 * pide solo identidad; un plugin como aero/sheets declara aquí su scope y solo
 * se solicita cuando la persona lo conecta, y solo si ese plugin está activo
 * (el listener lo registra en su boot()).
 *
 * Evento `aero.oauth.scopes` → ['google' => ['sheets' => ['label' => '…', 'scopes' => ['https://…']]]]
 */
class ScopeRegistry
{
    public static function all(string $provider): array
    {
        $out = [];
        foreach (array_filter(Event::dispatch('aero.oauth.scopes') ?: []) as $declared) {
            foreach (($declared[$provider] ?? []) as $key => $def) {
                $out[$key] = $def;
            }
        }

        return $out;
    }

    /** Traduce claves ('sheets') a scopes reales; descarta claves desconocidas. */
    public static function resolve(string $provider, array $keys): array
    {
        $all = self::all($provider);
        $scopes = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $scopes = array_merge($scopes, (array) $all[$key]['scopes']);
            }
        }

        return array_values(array_unique($scopes));
    }
}
