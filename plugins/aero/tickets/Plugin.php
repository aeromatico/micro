<?php namespace Aero\Tickets;

use Backend;
use System\Classes\PluginBase;

/**
 * Eventos y venta de entradas (Bolivia). Fase 1: creación y gestión de eventos
 * públicos, privados y mixtos. Independiente: sin dependencias duras.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Entradas y eventos',
            'description' => 'Crea y administra eventos públicos, privados y mixtos, con venta de entradas e invitaciones.',
            'author'      => 'Aero',
            'icon'        => 'icon-ticket',
        ];
    }

    public function boot(): void
    {
        $this->bootPaymentBridges();

        \View::addNamespace('aerotickets', __DIR__ . '/views');
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });
    }

    /** Enlaces blandos: pagos ↔ órdenes, aero/pay (confirmación automática del QR) y avisos al comprador. */
    protected function bootPaymentBridges(): void
    {
        \Event::listen('aero.tickets.orderCancelled', fn ($o) => \Aero\Tickets\Classes\PaymentService::onOrderCancelled($o));
        \Event::listen('aero.tickets.orderExpired', fn ($o) => \Aero\Tickets\Classes\PaymentService::onOrderExpired($o));

        // Comisiones: se devengan al aprobarse el pago y se anulan si el pago se devuelve.
        \Event::listen('aero.tickets.paymentApproved', fn ($p, $o) => \Aero\Tickets\Classes\CommissionLedger::accrue($o, $p));
        \Event::listen('aero.tickets.paymentRefundPending', fn ($p, $o) => \Aero\Tickets\Classes\CommissionLedger::void($o, 'Pago a devolver'));

        // Entradas confirmadas (gratis, invitación o pago aprobado) → correo con enlaces y PDF, una sola vez por orden.
        \Event::listen('aero.tickets.orderConfirmed', fn ($o) => \Aero\Tickets\Classes\TicketDelivery::auto($o));
        \Event::listen('aero.tickets.paymentRejected', fn ($p, $o) => \Aero\Tickets\Classes\BuyerNotifier::paymentRejected($p, $o));
        \Event::listen('aero.tickets.paymentRefundPending', fn ($p, $o) => \Aero\Tickets\Classes\BuyerNotifier::refundPending($p, $o));

        // QR bancario con API: el banco confirma → mismo proceso de aprobación, actor «proveedor».
        \Event::listen('aero.pay.paymentReceived', function ($payment, $qrCode) {
            try {
                \Aero\Tickets\Classes\PaymentService::confirmFromProvider(
                    'pay_qr', (string) $qrCode->internal_reference, (float) ($payment->amount ?: $qrCode->amount),
                    ['pay_payment_id' => $payment->id ?? null, 'bank' => $qrCode->bank_code]
                );
            } catch (\Throwable $e) {
                \Log::error('aero.tickets: falló la confirmación automática del QR ' . $qrCode->internal_reference . ': ' . $e->getMessage());
            }
        });
    }

    public function registerSchedule($schedule): void
    {
        // Publicado → En curso → Finalizado.
        // Reservas sin pagar vencidas → liberadas.
        $schedule->call(fn () => [\Aero\Tickets\Models\Order::expireLapsed(), \Aero\Tickets\Models\Ticket::expireLapsed()])->everyMinute()->name('aero-tickets-expire-reservations')->withoutOverlapping();
        $schedule->call(fn () => \Aero\Tickets\Models\Event::syncStatuses())->everyFiveMinutes()->name('aero-tickets-sync-status')->withoutOverlapping();
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'Entradas y eventos',
                'description' => 'Comisión por servicio por defecto de las entradas de pago.',
                'category'    => 'Sistema',
                'icon'        => 'icon-ticket',
                'class'       => \Aero\Tickets\Models\Settings::class,
                'order'       => 533,
                'permissions' => ['aero.tickets.admin.settings', 'aero.tickets.superadmin'],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.tickets.use' => [
                'tab'   => 'Entradas y eventos',
                'label' => 'Usar Entradas y eventos: crear, publicar y administrar sus eventos',
            ],
            'aero.tickets.payments' => [
                'tab'   => 'Entradas y eventos',
                'label' => 'Verificar pagos: aprobar, rechazar, registrar pagos manuales y reembolsos',
            ],
            'aero.tickets.admin.view' => ['tab' => 'Entradas · Plataforma', 'label' => 'Ver el panel global, los usuarios y la auditoría (solo lectura)'],
            'aero.tickets.admin.organizers' => ['tab' => 'Entradas · Plataforma', 'label' => 'Aprobar, rechazar y suspender organizadores; gestionar usuarios y compradores'],
            'aero.tickets.admin.events' => ['tab' => 'Entradas · Plataforma', 'label' => 'Moderar, suspender y cancelar eventos de cualquier organizador'],
            'aero.tickets.admin.finance' => ['tab' => 'Entradas · Plataforma', 'label' => 'Ver pagos y órdenes de todos los organizadores; reglas y libro de comisiones'],
            'aero.tickets.admin.settings' => ['tab' => 'Entradas · Plataforma', 'label' => 'Parámetros, categorías, ubicaciones y plantillas de aviso'],
            'aero.tickets.superadmin' => [
                'tab'   => 'Entradas y eventos',
                'label' => 'Administrar Entradas y eventos: categorías y eventos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'tickets' => [
                'label'       => 'Eventos',
                'url'         => Backend::url('aero/tickets/events'),
                'icon'        => 'icon-ticket',
                'iconSvg'     => null,
                'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin'],
                'order'       => 533,
                'sideMenu'    => [
                    'events'     => ['label' => 'Eventos', 'icon' => 'icon-calendar', 'url' => Backend::url('aero/tickets/events'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'ticket_types' => ['label' => 'Entradas', 'icon' => 'icon-ticket', 'url' => Backend::url('aero/tickets/tickettypes'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'my_organizer' => ['label' => 'Mi organización', 'icon' => 'icon-briefcase', 'url' => Backend::url('aero/tickets/myorganizer'), 'permissions' => ['aero.tickets.use']],
                    'orders'     => ['label' => 'Órdenes', 'icon' => 'icon-shopping-cart', 'url' => Backend::url('aero/tickets/orders'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'guests'     => ['label' => 'Invitados', 'icon' => 'icon-envelope', 'url' => Backend::url('aero/tickets/guests'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'payments'   => ['label' => 'Pagos', 'icon' => 'icon-money', 'url' => Backend::url('aero/tickets/payments'), 'permissions' => ['aero.tickets.payments', 'aero.tickets.superadmin']],
                    'payment_settings' => ['label' => 'Cobros', 'icon' => 'icon-cog', 'url' => Backend::url('aero/tickets/paymentsettings'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'attendees'  => ['label' => 'Asistentes y ventas', 'icon' => 'icon-users', 'url' => Backend::url('aero/tickets/attendees'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'scanners'   => ['label' => 'Escáneres', 'icon' => 'icon-qrcode', 'url' => Backend::url('aero/tickets/scanners'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'scans'      => ['label' => 'Accesos', 'icon' => 'icon-list', 'url' => Backend::url('aero/tickets/scans'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                    'checkin'    => ['label' => 'Control de ingreso', 'icon' => 'icon-check', 'url' => Backend::url('aero/tickets/checkin'), 'permissions' => ['aero.tickets.use', 'aero.tickets.superadmin']],
                ],
            ],
            'tickets_admin' => [
                'label'       => 'Entradas · Plataforma',
                'url'         => Backend::url('aero/tickets/admindashboard'),
                'icon'        => 'icon-shield',
                'iconSvg'     => null,
                'permissions' => ['aero.tickets.admin.view', 'aero.tickets.admin.organizers', 'aero.tickets.admin.events', 'aero.tickets.admin.finance', 'aero.tickets.admin.settings', 'aero.tickets.superadmin'],
                'order'       => 534,
                'sideMenu'    => [
                    'dashboard'  => ['label' => 'Panel global', 'icon' => 'icon-dashboard', 'url' => Backend::url('aero/tickets/admindashboard'), 'permissions' => ['aero.tickets.admin.view', 'aero.tickets.superadmin']],
                    'organizers' => ['label' => 'Organizadores', 'icon' => 'icon-briefcase', 'url' => Backend::url('aero/tickets/organizers'), 'permissions' => ['aero.tickets.admin.organizers', 'aero.tickets.admin.view', 'aero.tickets.superadmin']],
                    'events'     => ['label' => 'Moderación de eventos', 'icon' => 'icon-calendar', 'url' => Backend::url('aero/tickets/adminevents'), 'permissions' => ['aero.tickets.admin.events', 'aero.tickets.admin.view', 'aero.tickets.superadmin']],
                    'users'      => ['label' => 'Usuarios', 'icon' => 'icon-users', 'url' => Backend::url('aero/tickets/platformusers'), 'permissions' => ['aero.tickets.admin.organizers', 'aero.tickets.admin.view', 'aero.tickets.superadmin']],
                    'buyers'     => ['label' => 'Compradores', 'icon' => 'icon-shopping-cart', 'url' => Backend::url('aero/tickets/buyers'), 'permissions' => ['aero.tickets.admin.organizers', 'aero.tickets.admin.view', 'aero.tickets.superadmin']],
                    'commission_rules' => ['label' => 'Reglas de comisión', 'icon' => 'icon-money', 'url' => Backend::url('aero/tickets/commissionrules'), 'permissions' => ['aero.tickets.admin.finance', 'aero.tickets.superadmin']],
                    'commission_entries' => ['label' => 'Comisiones generadas', 'icon' => 'icon-money', 'url' => Backend::url('aero/tickets/commissionentries'), 'permissions' => ['aero.tickets.admin.finance', 'aero.tickets.superadmin']],
                    'categories' => ['label' => 'Categorías', 'icon' => 'icon-tags', 'url' => Backend::url('aero/tickets/categories'), 'permissions' => ['aero.tickets.admin.settings', 'aero.tickets.superadmin']],
                    'locations'  => ['label' => 'Ubicaciones', 'icon' => 'icon-map-marker', 'url' => Backend::url('aero/tickets/locations'), 'permissions' => ['aero.tickets.admin.settings', 'aero.tickets.superadmin']],
                    'templates'  => ['label' => 'Plantillas de aviso', 'icon' => 'icon-envelope', 'url' => Backend::url('aero/tickets/templates'), 'permissions' => ['aero.tickets.admin.settings', 'aero.tickets.superadmin']],
                    'settings'   => ['label' => 'Parámetros y políticas', 'icon' => 'icon-cog', 'url' => Backend::url('system/settings/update/aero/tickets/settings'), 'permissions' => ['aero.tickets.admin.settings', 'aero.tickets.superadmin']],
                    'audit'      => ['label' => 'Auditoría', 'icon' => 'icon-list', 'url' => Backend::url('aero/tickets/auditlog'), 'permissions' => ['aero.tickets.admin.view', 'aero.tickets.superadmin']],
                ],
            ],
        ];
    }
}
