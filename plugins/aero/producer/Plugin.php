<?php namespace Aero\Producer;

use Backend;
use System\Classes\PluginBase;

/**
 * Productor de contenido (primero video, luego audio). Reúne el flujo de
 * video-cli: proyectos con guion y voz, y los datos que piden las redes
 * (título, descripción, etiquetas, categoría) listos para aprobar. Independiente:
 * la entrega a YouTube es un puente blando hacia Aero.Youtube.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Productor',
            'description' => 'Producción de video con guion, voz y datos listos para redes; se publica con YouTube.',
            'author'      => 'Aero',
            'icon'        => 'icon-plug',
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.producer.superadmin' => [
                'tab'   => 'Productor',
                'label' => 'Administrar el Productor: proyectos de video de la plataforma',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $admin = ['aero.producer.superadmin'];

        return [
            'producer' => [
                'label'       => 'Productor',
                'url'         => Backend::url('aero/producer/projects'),
                'icon'        => 'icon-plug',
                'iconSvg'     => null,
                'permissions' => $admin,
                'order'       => 528,
                'sideMenu'    => [
                    'projects' => ['label' => 'Proyectos', 'icon' => 'icon-plug', 'url' => Backend::url('aero/producer/projects'), 'permissions' => $admin],
                    'credentials' => ['label' => 'Llaves de proveedores', 'icon' => 'icon-key', 'url' => Backend::url('aero/producer/credentials'), 'permissions' => $admin],
                ],
            ],
        ];
    }
}
