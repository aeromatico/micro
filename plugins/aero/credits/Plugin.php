<?php namespace Aero\Credits;

use Aero\Credits\Classes\Credits;
use Backend;
use BackendAuth;
use Cache;
use Event;
use System\Classes\PluginBase;

/**
 * Sistema de créditos prepago multi-color (azul/rojo, ver CreditType) para
 * acciones de IA y servicios conectados. Ningún plugin lo requiere ni él
 * requiere a ninguno — toda integración es `class_exists()` + evento, mismo
 * patrón que Aero.Sites usa con Aero.Hello/Aero.Api (ver bootHelloIntegration).
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.credits::lang.plugin.name',
            'description' => 'aero.credits::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-diamond',
            'homepage'    => 'https://micro.clouds.com.bo',
        ];
    }

    public function boot(): void
    {
        $this->bootNavbarWidget();
        $this->bootConnectorIntegration();
    }

    /**
     * Inyecta un pill compacto de consumo global justo al lado del site
     * switcher nativo (mismo punto de extensión que usa October core:
     * modules/backend/layouts/_mainmenu.php → backend.layout.extendMainMenuToolbar).
     * Solo superadmins lo ven.
     */
    protected function bootNavbarWidget(): void
    {
        Event::listen('backend.layout.extendMainMenuToolbar', function () {
            $user = BackendAuth::getUser();

            if (!$user || !$user->is_superuser) {
                return '';
            }

            $totals = Cache::remember('aero.credits.navbar_totals', 60, function () {
                return \Aero\Credits\Models\CreditType::active()->get()->map(function ($type) {
                    $consumed = \Aero\Credits\Models\CreditTransaction::where('credit_type_id', $type->id)
                        ->where('delta', '<', 0)
                        ->whereDate('created_at', today())
                        ->sum('delta');

                    return [
                        'label'    => $type->label,
                        'color'    => $type->color,
                        'consumed' => abs($consumed),
                    ];
                })->all();
            });

            return (new \Backend\Classes\Controller)->makePartial(
                '$/aero/credits/partials/_navbar_widget.htm',
                ['totals' => $totals]
            );
        });
    }

    /**
     * Aero.Connector no sabe nada de créditos: solo dispara
     * 'aero.connector.afterRun' (evento genérico) con el Connector y su log.
     * Acá se escucha y, si el connector tiene `credit_cost` configurado, se
     * cobra al tenant resuelto del contexto backend actual.
     */
    protected function bootConnectorIntegration(): void
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return;
        }

        Event::listen('aero.connector.afterRun', function ($connector, $response) {
            $cost = (int) ($connector->credit_cost ?? 0);

            if ($cost <= 0 || !($response->successful ?? true)) {
                return;
            }

            $tenantId = Credits::resolveCurrentTenantId();

            if (!$tenantId) {
                return;
            }

            $type = $connector->credit_type_id
                ? \Aero\Credits\Models\CreditType::find($connector->credit_type_id)
                : \Aero\Credits\Models\CreditType::active()->first();

            if (!$type) {
                return;
            }

            try {
                Credits::chargeRaw($tenantId, $type, $cost, "connector.{$connector->id}", [
                    'source_plugin' => 'Aero.Connector',
                    'reason'        => "Llamada a conector: {$connector->name}",
                ]);
            }
            catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
                // No se puede deshacer una llamada saliente ya ejecutada: solo se
                // deja registro de que el tenant se quedó sin saldo para este
                // conector, para que el superadmin lo revise. No relanza.
                \Log::warning("Aero.Credits: {$e->getMessage()} (connector #{$connector->id}, tenant {$tenantId})");
            }
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.credits.superadmin' => [
                'tab'   => 'aero.credits::lang.plugin.name',
                'label' => 'aero.credits::lang.permissions.superadmin',
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'aero.credits::lang.plugin.name',
                'description' => 'aero.credits::lang.settings.description',
                'category'    => 'Sistema',
                'icon'        => 'icon-diamond',
                'class'       => \Aero\Credits\Models\Settings::class,
                'order'       => 520,
                'permissions' => ['aero.credits.superadmin'],
                'keywords'    => 'creditos credits ia billing saldo',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'credits' => [
                'label'       => 'aero.credits::lang.menu.credits',
                'url'         => Backend::url('aero/credits/creditaccounts'),
                'icon'        => 'icon-diamond',
                'permissions' => ['aero.credits.superadmin'],
                'order'       => 570,
                'sideMenu'    => [
                    'creditaccounts' => [
                        'label'       => 'aero.credits::lang.menu.creditaccounts',
                        'icon'        => 'icon-university',
                        'url'         => Backend::url('aero/credits/creditaccounts'),
                        'permissions' => ['aero.credits.superadmin'],
                    ],
                    'credittransactions' => [
                        'label'       => 'aero.credits::lang.menu.credittransactions',
                        'icon'        => 'icon-list-alt',
                        'url'         => Backend::url('aero/credits/credittransactions'),
                        'permissions' => ['aero.credits.superadmin'],
                    ],
                    'creditactions' => [
                        'label'       => 'aero.credits::lang.menu.creditactions',
                        'icon'        => 'icon-tags',
                        'url'         => Backend::url('aero/credits/creditactions'),
                        'permissions' => ['aero.credits.superadmin'],
                    ],
                    'credittypes' => [
                        'label'       => 'aero.credits::lang.menu.credittypes',
                        'icon'        => 'icon-paint-brush',
                        'url'         => Backend::url('aero/credits/credittypes'),
                        'permissions' => ['aero.credits.superadmin'],
                    ],
                ],
            ],
        ];
    }

    public function registerReportWidgets(): array
    {
        return [
            \Aero\Credits\ReportWidgets\MyCredits::class => [
                'label'   => 'aero.credits::lang.widget.my_credits',
                'context' => 'dashboard',
            ],
            \Aero\Credits\ReportWidgets\GlobalCredits::class => [
                'label'       => 'aero.credits::lang.widget.global_credits',
                'context'     => 'dashboard',
                'permissions' => ['aero.credits.superadmin'],
            ],
        ];
    }
}
