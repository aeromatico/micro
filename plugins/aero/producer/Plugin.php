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
            'aero.producer.use' => [
                'tab'   => 'Productor',
                'label' => 'Pedir videos con los estilos de la plataforma y ver los suyos',
            ],
            'aero.producer.superadmin' => [
                'tab'   => 'Productor',
                'label' => 'Administrar el Productor: proyectos, estilos, voces y pedidos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $admin = ['aero.producer.superadmin'];
        $tenant = ['aero.producer.use', 'aero.producer.superadmin'];

        return [
            'producer' => [
                'label'       => 'Productor',
                'url'         => Backend::url('aero/producer/studio'),
                'icon'        => 'icon-plug',
                'iconSvg'     => null,
                'permissions' => $tenant,
                'order'       => 528,
                'sideMenu'    => [
                    'studio'      => ['label' => 'Nuevo video', 'icon' => 'icon-plug', 'url' => Backend::url('aero/producer/studio'), 'permissions' => $tenant],
                    'orders'      => ['label' => 'Mis videos', 'icon' => 'icon-list', 'url' => Backend::url('aero/producer/orders'), 'permissions' => $tenant],
                    'projects'    => ['label' => 'Proyectos', 'icon' => 'icon-plug', 'url' => Backend::url('aero/producer/projects'), 'permissions' => $admin],
                    'styles'      => ['label' => 'Estilos', 'icon' => 'icon-list', 'url' => Backend::url('aero/producer/styles'), 'permissions' => $admin],
                    'voices'      => ['label' => 'Voces', 'icon' => 'icon-list', 'url' => Backend::url('aero/producer/voices'), 'permissions' => $admin],
                    'credentials' => ['label' => 'Llaves de proveedores', 'icon' => 'icon-key', 'url' => Backend::url('aero/producer/credentials'), 'permissions' => $admin],
                ],
            ],
        ];
    }
}
