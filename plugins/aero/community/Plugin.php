<?php namespace Aero\Community;

use Backend;
use BackendAuth;
use Event;
use System\Classes\PluginBase;

/**
 * Comunidad: foro simple entre dueños de tenants, sugerencias con compromiso
 * de respuesta y un botón de soporte rápido (con URL y captura) presente en
 * todo el panel. Independiente: Aero.Sites solo aporta el nombre del tenant.
 */
class Plugin extends PluginBase
{
    public const ASSET_VERSION = '1.3.1';

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Comunidad',
            'description' => 'Foro simple, sugerencias con respuesta garantizada y soporte rápido con captura desde cualquier pantalla.',
            'author'      => 'Aero',
            'icon'        => 'icon-comments',
        ];
    }

    public function boot(): void
    {
        // El botón de soporte rápido aparece en todo el panel para quien use Comunidad.
        Event::listen('backend.page.beforeDisplay', function ($controller) {
            $user = BackendAuth::getUser();
            if (!$user || request()->ajax()) {
                return;
            }
            if (!$user->hasAccess(['aero.community.use']) && !$user->hasAccess('aero.community.superadmin')) {
                return;
            }

            $controller->addCss('/plugins/aero/community/assets/css/quick-support.css?cb=' . self::ASSET_VERSION);
            $controller->addJs('/plugins/aero/community/assets/js/quick-support.js?cb=' . self::ASSET_VERSION, [
                'data-endpoint' => Backend::url('aero/community/quick'),
                'data-posts'    => Backend::url('aero/community/posts'),
                'data-html2canvas' => '/plugins/aero/community/assets/vendor/html2canvas.min.js?v=1.4.1',
            ]);
        });
    }

    public function registerSchedule($schedule): void
    {
        // Avisa (una vez) de lo que pasó el plazo (minutos) sin respuesta del equipo.
        $schedule->call(function () {
            \Aero\Community\Models\Post::awaitingResponse()
                ->whereNull('overdue_notified_at')
                ->where('sla_due_at', '<', now())
                ->get()
                ->each(function ($post) {
                    $post->overdue_notified_at = now();
                    $post->saveQuietly();
                    Event::fire('aero.community.postOverdue', [$post]);
                });
        })->everyFiveMinutes();
    }

    public function registerPermissions(): array
    {
        return [
            'aero.community.use' => [
                'tab'   => 'Comunidad',
                'label' => 'Participar en la comunidad: foro, sugerencias, votos y soporte rápido',
            ],
            'aero.community.superadmin' => [
                'tab'   => 'Comunidad',
                'label' => 'Moderar la comunidad: responder, cambiar estados y ver todos los reportes',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.community.use', 'aero.community.superadmin'];

        return [
            'community' => [
                'label'       => 'Comunidad',
                'url'         => Backend::url('aero/community/posts'),
                'icon'        => 'icon-comments',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 531,
                'sideMenu'    => [
                    'feed'  => ['label' => 'Foro', 'icon' => 'icon-comments', 'url' => Backend::url('aero/community/posts'), 'permissions' => $perms],
                    'ideas' => ['label' => 'Sugerencias', 'icon' => 'icon-star', 'url' => Backend::url('aero/community/posts?type=idea'), 'permissions' => $perms],
                    'mine'  => ['label' => 'Mis reportes', 'icon' => 'icon-life-ring', 'url' => Backend::url('aero/community/posts?mine=1'), 'permissions' => $perms],
                    'pending' => ['label' => 'Sin responder', 'icon' => 'icon-clock', 'url' => Backend::url('aero/community/posts?pending=1'), 'permissions' => ['aero.community.superadmin']],
                ],
            ],
        ];
    }
}
