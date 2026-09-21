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
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('credits:reconcile', \Aero\Credits\Console\Reconcile::class);
        $this->registerConsoleCommand('credits:sweep-holds', \Aero\Credits\Console\SweepHolds::class);
        $this->registerConsoleCommand('credits:expire-purchases', \Aero\Credits\Console\ExpirePurchases::class);
        $this->registerConsoleCommand('credits:verify', \Aero\Credits\Console\Verify::class);
        $this->registerConsoleCommand('credits:reset-ledger', \Aero\Credits\Console\ResetLedger::class);
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('credits:sweep-holds')->everyFiveMinutes();
        $schedule->command('credits:expire-purchases')->everyMinute();
        $schedule->command('credits:verify')->dailyAt('04:10');
    }

    public function boot(): void
    {
        $this->bootNavbarWidget();
        $this->bootConnectorIntegration();
        $this->bootPurchaseIntegration();
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

            if (!$user) {
                return '';
            }

            if ($user->is_superuser) {
                // Vista de plataforma: monedas ACTIVAS entre todos los tenants
                // (lo que hoy está en circulación) + detalle de lo otorgado.
                $rows = Cache::remember('aero.credits.navbar_summary', 30, function () {
                    return collect(Credits::summary())->map(function ($s) {
                        return [
                            'color' => $s['color'],
                            'value' => $s['active'],
                            'title' => "{$s['label']}\nActivas: " . number_format($s['active'], 0, ',', '.')
                                . "\nVendidas: " . number_format($s['sold'], 0, ',', '.')
                                . "\nRegaladas: " . number_format($s['gifted'] + $s['plan_grant'], 0, ',', '.')
                                . "\nConsumidas hoy: " . number_format($s['consumed_today'], 0, ',', '.'),
                        ];
                    })->values()->all();
                });

                return (new \Backend\Classes\Controller)->makePartial('$/aero/credits/partials/_navbar_widget.htm', [
                    'rows'      => $rows,
                    'link'      => Backend::url('aero/credits/summary'),
                    'plusUrl'   => Backend::url('aero/credits/creditaccounts'),
                    'plusTitle' => 'Otorgar monedas',
                    'title'     => 'Monedas activas entre todos los tenants',
                ]);
            }

            // Vista del tenant: su saldo exacto, siempre en vivo (sin caché).
            $tenantId = Credits::resolveCurrentTenantId();

            if (!$tenantId) {
                return '';
            }

            $balances = Credits::balances($tenantId);
            $rows = \Aero\Credits\Models\CreditType::active()->get()->map(fn ($t) => [
                'color' => $t->color,
                'value' => $balances[$t->code] ?? 0,
                'title' => "{$t->label}: " . number_format($balances[$t->code] ?? 0, 0, ',', '.'),
            ])->all();

            // "Wallet" vive en el menú inferior (antes era «Mis monedas» en el menú lateral).
            $walletItem = '<div class="toolbar-item fix-width" style="padding:0"><ul class="mainmenu-items" data-main-menu style="margin:0;padding:0"><li class="mainmenu-item" title="Wallet"><a href="' . e(Backend::url('aero/credits/wallet')) . '"><span class="nav-icon"><i class="icon-money"></i></span><span class="nav-label">Wallet</span></a></li></ul></div>';

            return (new \Backend\Classes\Controller)->makePartial('$/aero/credits/partials/_navbar_widget.htm', [
                'rows'      => $rows,
                'link'      => Backend::url('aero/credits/wallet'),
                'plusUrl'   => Backend::url('aero/credits/wallet'),
                'plusTitle' => 'Recargar monedas',
                'title'     => 'Tu saldo de monedas',
            ]) . $walletItem;
        });
    }

    /**
     * Aero.Connector no sabe nada de créditos: solo dispara dos eventos
     * genéricos con un ArrayObject de contexto compartido.
     *  - beforeRun: acá se cobra con hold; si no hay saldo se llena
     *    $ctx['veto'] y ConnectorClient no ejecuta la llamada (no cobra gratis).
     *  - afterRun: la llamada exitosa liquida el hold; la fallida lo reembolsa.
     * Solo cobra si el connector tiene `credit_cost` y hay tenant resoluble.
     */
    protected function bootConnectorIntegration(): void
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return;
        }

        Event::listen('aero.connector.beforeRun', function ($connector, $ctx) {
            $cost = (int) ($connector->credit_cost ?? 0);

            if ($cost <= 0 || !($tenantId = Credits::resolveCurrentTenantId())) {
                return;
            }

            $type = $connector->credit_type_id
                ? \Aero\Credits\Models\CreditType::find($connector->credit_type_id)
                : \Aero\Credits\Models\CreditType::active()->first();

            if (!$type) {
                return;
            }

            try {
                $ctx['credit_tx'] = Credits::chargeRaw($tenantId, $type, $cost, "connector.{$connector->id}", [
                    'source_plugin' => 'Aero.Connector',
                    'reason'        => "Llamada a conector: {$connector->name}",
                    'hold_ttl'      => Credits::DEFAULT_HOLD_TTL,
                ]);
            }
            catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
                $ctx['veto'] = $e->getMessage();
            }
        });

        Event::listen('aero.connector.afterRun', function ($connector, $response, $ctx = null) {
            $tx = $ctx['credit_tx'] ?? null;

            if (!$tx) {
                return;
            }

            if ($response->successful ?? true) {
                Credits::settle($tx);
            }
            else {
                Credits::refund($tx, 'Llamada a conector fallida: ' . $connector->name);
            }
        });
    }

    /**
     * Pago confirmado por aero/pay (webhook o conciliación): acredita las
     * monedas de la recarga cuyo QR es este. Nunca deja escapar una excepción
     * para no romper el procesamiento del pago en aero/pay.
     */
    protected function bootPurchaseIntegration(): void
    {
        Event::listen('aero.pay.paymentReceived', function ($payment, $qrCode) {
            $purchase = \Aero\Credits\Models\CreditPurchase::where('payment_reference', $qrCode->internal_reference)->first();

            if (!$purchase) {
                return;
            }

            try {
                \Aero\Credits\Classes\Recharges::settle($purchase, (float) $payment->amount);
            }
            catch (\Throwable $e) {
                \Log::error("Aero.Credits: fallo al acreditar la recarga #{$purchase->id} tras pago confirmado: " . $e->getMessage());
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
                    'summary' => [
                        'label'       => 'Resumen contable',
                        'icon'        => 'icon-bar-chart',
                        'url'         => Backend::url('aero/credits/summary'),
                        'permissions' => ['aero.credits.superadmin'],
                    ],
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
