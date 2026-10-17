<?php namespace Aero\Rgpd;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Aviso de cookies compatible con RGPD para la plataforma y para los sitios
 * de los tenants. Una fila de configuración por alcance (tenant_id NULL =
 * plataforma): activa en la plataforma, apagada por defecto en los tenants.
 * Independiente: Aero.Sites es dependencia blanda (sin él todo es "plataforma").
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'RGPD',
            'description' => 'Aviso y registro de consentimiento de cookies para la plataforma y los sitios de los tenants.',
            'author'      => 'Aero',
            'icon'        => 'icon-shield',
        ];
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        // Al crearse un tenant nace con el aviso apagado (se crea la fila perezosamente
        // al abrir la pantalla); no hace falta enganchar nada más.
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Rgpd\Components\Banner::class => 'rgpdBanner',
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.rgpd.use' => [
                'tab'   => 'RGPD',
                'label' => 'Configurar el aviso de cookies de su sitio y ver el registro de consentimientos',
            ],
            'aero.rgpd.superadmin' => [
                'tab'   => 'RGPD',
                'label' => 'Administrar el aviso de cookies de la plataforma y el de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.rgpd.use', 'aero.rgpd.superadmin'];

        return [
            'rgpd' => [
                'label'       => 'Cookies (RGPD)',
                'url'         => Backend::url('aero/rgpd/configs'),
                'icon'        => 'icon-shield',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 940,
                'sideMenu'    => [
                    'configs'  => ['label' => 'Aviso de cookies', 'icon' => 'icon-cog', 'url' => Backend::url('aero/rgpd/configs'), 'permissions' => $perms],
                    'consents' => ['label' => 'Consentimientos', 'icon' => 'icon-list-ul', 'url' => Backend::url('aero/rgpd/consents'), 'permissions' => $perms],
                ],
            ],
        ];
    }
}
