<?php namespace Aero\Medical;

use Backend;
use System\Classes\PluginBase;

/**
 * Historia clínica electrónica por negocio (tenant), humana o veterinaria: el
 * núcleo clínico es común y el modo (Configuración) fija vocabulario, campos y
 * módulos. Acoplamiento blando con Aero.Office (clientes/tutores, profesionales,
 * reservas) por class_exists() y eventos aero.*. Datos de salud: cifrados,
 * auditados y nunca en eventos hacia webhooks.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Historia clínica',
            'description' => 'Historia clínica electrónica para consultorios y veterinarias: pacientes, consultas, recetas y seguimiento.',
            'author'      => 'Aero',
            'icon'        => 'icon-heartbeat',
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.medical.use' => [
                'tab'   => 'Historia clínica',
                'label' => 'Administrar el módulo: pacientes (datos administrativos) y bitácora',
            ],
            'aero.medical.settings' => [
                'tab'   => 'Historia clínica',
                'label' => 'Configuración del módulo (modo humano/animal, plantillas) y bitácora (propietario)',
            ],
            'aero.medical.professional' => [
                'tab'   => 'Historia clínica',
                'label' => 'Profesional: ver el contenido clínico, escribir y firmar notas',
            ],
            'aero.medical.reception' => [
                'tab'   => 'Historia clínica',
                'label' => 'Recepción: datos administrativos de pacientes, sin contenido clínico',
            ],
            'aero.medical.superadmin' => [
                'tab'   => 'Historia clínica',
                'label' => 'Administrar Historia clínica: datos de todos los negocios',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $all = ['aero.medical.use', 'aero.medical.reception', 'aero.medical.professional', 'aero.medical.superadmin'];
        $owner = ['aero.medical.settings', 'aero.medical.superadmin'];
        $item = fn (string $label, string $icon, string $path, array $p) => [
            'label' => $label, 'icon' => $icon, 'url' => Backend::url('aero/medical/' . $path), 'permissions' => $p,
        ];

        return [
            'medical' => [
                'label'       => 'Historia clínica',
                'url'         => Backend::url('aero/medical/patients'),
                'icon'        => 'icon-heartbeat',
                'iconSvg'     => null,
                'permissions' => $all,
                'order'       => 526,
                'sideMenu'    => [
                    'patients'  => $item('Pacientes', 'icon-users', 'patients', $all),
                    'auditlogs' => $item('Bitácora', 'icon-list', 'auditlogs', $owner),
                    'settings'  => $item('Configuración', 'icon-cog', 'settings', $owner),
                ],
            ],
        ];
    }
}
