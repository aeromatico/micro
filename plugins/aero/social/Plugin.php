<?php namespace Aero\Social;

use Backend;
use System\Classes\PluginBase;

/**
 * Redes sociales para tenants: cada cuenta conecta SUS redes (tokens propios,
 * cifrados, por tenant) y publica o programa posts en varias a la vez. Cobra
 * créditos por publicación y red (`social.post.{red}`) si Aero.Credits está.
 * Fase 1: Facebook + Instagram. Ver classes/Networks.php para sumar redes.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Redes sociales',
            'description' => 'Conecta tus redes y publica o programa posts desde un solo lugar.',
            'author'      => 'Aero',
            'icon'        => 'icon-share-alt',
        ];
    }

    public function registerSchedule($schedule): void
    {
        $schedule->call(function () {
            \Aero\Social\Classes\Publisher::sweep();
            \Aero\Social\Classes\Publisher::dispatchDue();
        })->everyMinute()->name('aero-social-dispatch')->withoutOverlapping();

        $schedule->call(fn () => \Aero\Social\Classes\Publisher::checkAccounts())
            ->dailyAt('04:20')->name('aero-social-check-accounts')->withoutOverlapping();
    }

    public function registerPermissions(): array
    {
        return [
            'aero.social.use' => [
                'tab'   => 'Redes sociales',
                'label' => 'Conectar sus redes sociales y publicar o programar posts',
            ],
            'aero.social.superadmin' => [
                'tab'   => 'Redes sociales',
                'label' => 'Administrar redes sociales: ver publicaciones y conexiones de todas las cuentas',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.social.use', 'aero.social.superadmin'];

        return [
            'social' => [
                'label'       => 'Redes sociales',
                'url'         => Backend::url('aero/social/posts'),
                'icon'        => 'icon-share-alt',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 537,
                'sideMenu'    => [
                    'posts'       => ['label' => 'Publicaciones', 'icon' => 'icon-paper-plane', 'url' => Backend::url('aero/social/posts'), 'permissions' => $perms],
                    'connections' => ['label' => 'Conexiones', 'icon' => 'icon-link', 'url' => Backend::url('aero/social/connections'), 'permissions' => $perms],
                ],
            ],
        ];
    }
}
