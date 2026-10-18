<?php namespace Aero\Plan;

use Backend;
use System\Classes\PluginBase;

/**
 * Plan de 7 días: acompañamiento guiado en 5 pasos. El dueño del tenant
 * completa las solicitudes de cada paso, el equipo de la plataforma
 * (superadmins) responde y aprueba, y recién entonces se abre el siguiente.
 * Es opcional: el dueño puede dejarlo (pausa) y seguir por su cuenta.
 *
 * Eventos (los consume Aero.Notify): aero.plan.started, stageSubmitted,
 * stageApproved, changesRequested, messagePosted, paused, resumed, completed.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.plan::lang.plugin.name',
            'description' => 'aero.plan::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-list-alt',
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.plan.use' => [
                'tab'   => 'Plan de 7 días',
                'label' => 'Usar el Plan de 7 días: completar los pasos, escribir al equipo y pausar su plan',
            ],
            'aero.plan.superadmin' => [
                'tab'   => 'Plan de 7 días',
                'label' => 'Administrar planes: revisar, responder y aprobar los pasos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'plan' => [
                'label'       => 'Plan de 7 días',
                'url'         => Backend::url('aero/plan/plan'),
                'icon'        => 'icon-list-alt',
                'iconSvg'     => null,
                'permissions' => ['aero.plan.use', 'aero.plan.superadmin'],
                'order'       => 90,
                'sideMenu'    => [
                    'mi-plan' => ['label' => 'Mi plan', 'icon' => 'icon-list-alt', 'url' => Backend::url('aero/plan/plan'), 'permissions' => ['aero.plan.use']],
                    'plans'   => ['label' => 'Planes de los tenants', 'icon' => 'icon-inbox', 'url' => Backend::url('aero/plan/plans'), 'permissions' => ['aero.plan.superadmin']],
                ],
            ],
        ];
    }
}
