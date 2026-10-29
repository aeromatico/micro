<?php namespace Aero\Viral;

use Backend;
use System\Classes\PluginBase;

/**
 * Viral: postulaciones de creadores de video vertical (shorts, lives y edición).
 * Solo de la plataforma (sin tenants). Los postulantes son usuarios de RainLab.User;
 * la identidad se verifica con KYC (driver manual por ahora).
 */
class Plugin extends PluginBase
{
    public $require = ['RainLab.User'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Viral',
            'description' => 'Postulaciones de creadores de shorts, lives y edición, con revisión de muestra y verificación de identidad.',
            'author'      => 'Aero',
            'icon'        => 'icon-star',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('viral.s3check', \Aero\Viral\Console\S3Check::class);
    }

    public function registerComponents(): array
    {
        return [\Aero\Viral\Components\ViralApply::class => 'viralApply'];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.viral.superadmin' => [
                'tab'   => 'Viral',
                'label' => 'Administrar Viral: revisar postulaciones, muestras y verificaciones de identidad',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'viral' => [
                'label'       => 'Viral',
                'url'         => Backend::url('aero/viral/applications'),
                'icon'        => 'icon-star',
                'iconSvg'     => null,
                'permissions' => ['aero.viral.superadmin'],
                'order'       => 517,
                'sideMenu'    => [
                    'applications' => [
                        'label' => 'Postulaciones', 'icon' => 'icon-users',
                        'url' => Backend::url('aero/viral/applications'),
                        'permissions' => ['aero.viral.superadmin'],
                    ],
                ],
            ],
        ];
    }
}
