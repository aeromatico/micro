<?php namespace Aero\Sheets;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Importa/exporta campos de modelos Aero desde/hacia Google Sheets. El
 * superadmin decide qué modelos y qué campos son sincronizables; cada persona
 * usa su propia cuenta de Google (aero/oauth), y el permiso de Sheets se pide
 * solo cuando conecta desde aquí — nunca en el login normal.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Oauth'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Google Sheets',
            'description' => 'Sincroniza campos de modelos con hojas de cálculo de Google (importar y exportar).',
            'author'      => 'Aero',
            'icon'        => 'icon-table',
        ];
    }

    public function boot(): void
    {
        // El permiso de Sheets existe como opción solo mientras este plugin esté activo.
        Event::listen('aero.oauth.scopes', function () {
            return ['google' => ['sheets' => [
                'label'  => 'Google Sheets (leer y escribir tus hojas de cálculo)',
                'scopes' => [\Aero\Sheets\Classes\SheetsClient::SCOPE],
            ]]];
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.sheets.use' => [
                'tab'   => 'Google Sheets',
                'label' => 'Importar/exportar con Google Sheets usando las fuentes autorizadas',
            ],
            'aero.sheets.superadmin' => [
                'tab'   => 'Google Sheets',
                'label' => 'Administrar fuentes y campos sincronizables; ver todos los mapeos',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.sheets.use', 'aero.sheets.superadmin'];

        return [
            'sheets' => [
                'label'       => 'Google Sheets',
                'url'         => Backend::url('aero/sheets/mappings'),
                'icon'        => 'icon-table',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 530,
                'sideMenu'    => [
                    'mappings' => ['label' => 'Mapeos', 'icon' => 'icon-exchange', 'url' => Backend::url('aero/sheets/mappings'), 'permissions' => $perms],
                    'runs'     => ['label' => 'Historial', 'icon' => 'icon-history', 'url' => Backend::url('aero/sheets/runs'), 'permissions' => $perms],
                    'sources'  => ['label' => 'Fuentes y campos', 'icon' => 'icon-database', 'url' => Backend::url('aero/sheets/sources'), 'permissions' => ['aero.sheets.superadmin']],
                    'connect'  => ['label' => 'Cuenta de Google', 'icon' => 'icon-key', 'url' => Backend::url('aero/oauth/connections'), 'permissions' => $perms],
                ],
            ],
        ];
    }
}
