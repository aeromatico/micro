<?php namespace Aero\Shop\Controllers;

use Aero\Shop\Classes\EtaCalculator;
use Aero\Shop\Classes\OrderNotifier;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\ShopSettings;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Db;

/**
 * Pantalla de cocina: pedidos del restaurante en columnas (nuevo, preparando,
 * listo) que se refrescan solas y avanzan con un toque.
 */
class Kitchen extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.shop.manage_orders'];

    protected const FLOW = ['new', 'preparing', 'ready', 'delivered'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shop', 'tienda', 'shop-cocina');
        $this->pageTitle = 'Cocina';
    }

    public function index()
    {
        $tenantId = $this->getCurrentTenantId();
        $this->vars['isRestaurant'] = $tenantId && ShopSettings::isRestaurantForTenant($tenantId);
        $this->vars['board'] = $this->vars['isRestaurant'] ? $this->makePartial('board', $this->boardData($tenantId)) : '';
    }

    public function onRefresh()
    {
        return ['#kitchen-board' => $this->makePartial('board', $this->boardData($this->getCurrentTenantId()))];
    }

    /** @param string $to new|preparing|ready|delivered */
    public function onMove()
    {
        $tenantId = $this->getCurrentTenantId();
        $to = (string) post('to');
        if (!in_array($to, self::FLOW, true)) {
            return $this->onRefresh();
        }

        $order = Order::forTenant($tenantId)->whereNotNull('kitchen_status')->where('status', '!=', 'cancelled')->find((int) post('order_id'));
        if (!$order || $order->kitchen_status === $to) {
            return $this->onRefresh();
        }

        $from = $order->kitchen_status;
        $userId = BackendAuth::getUser()->id;

        $settings = ShopSettings::where('tenant_id', $tenantId)->first();
        $eta = (int) post('eta');
        if ($to === 'preparing' && $from === 'new') {
            // Al aceptar arranca el reloj: cocina confirma (o ajusta) el tiempo propuesto.
            $eta = $eta > 0 ? min(240, $eta) : EtaCalculator::minutes($order, $settings);
        }

        Db::transaction(function () use ($order, $to, $from, $userId, $eta) {
            if ($to === 'preparing' && $from === 'new') {
                $order->accepted_at = now();
                $order->promised_at = EtaCalculator::promisedAt($order, $eta);
            }
            if ($to === 'new') {
                $order->accepted_at = null;
                $order->promised_at = null;
            }
            $order->kitchen_status = $to;
            $order->kitchen_updated_at = now();
            if ($to === 'ready') {
                $order->ready_at = now();
            }
            if ($to === 'delivered' && in_array($order->status, ['pending', 'paid'], true)) {
                $prev = $order->status;
                $order->status = 'fulfilled';
                $order->fulfilled_at = now();
                $order->status_history()->create(['from_status' => $prev, 'to_status' => 'fulfilled', 'changed_by_backend_user_id' => $userId, 'note' => 'cocina: entregado']);
            }
            $order->save();
        });

        if ($to === 'ready' && $from !== 'delivered') {
            OrderNotifier::fire($order->fresh(), 'ready');
        }

        return $this->onRefresh();
    }

    /** Retraso: cocina avisa que tomará unos minutos más y la hora prometida se corre. */
    public function onExtend()
    {
        $order = Order::forTenant($this->getCurrentTenantId())->where('kitchen_status', 'preparing')->find((int) post('order_id'));
        $minutes = (int) post('minutes');
        if ($order && $order->promised_at && $minutes > 0 && $minutes <= 60) {
            $order->promised_at = $order->promised_at->copy()->addMinutes($minutes);
            $order->save();
        }

        return $this->onRefresh();
    }

    /** Modo ocupado: suma minutos a todos los pedidos nuevos que se acepten. */
    public function onToggleBusy()
    {
        $settings = ShopSettings::where('tenant_id', $this->getCurrentTenantId())->first();
        if ($settings) {
            $cfg = $settings->restaurant();
            $cfg['busy'] = empty($cfg['busy']);
            $settings->restaurant_config = $cfg;
            $settings->save();
        }

        return $this->onRefresh();
    }

    protected function boardData(?int $tenantId): array
    {
        $base = Order::forTenant($tenantId)->where('status', '!=', 'cancelled')
            ->with(['items.product', 'customer', 'currency'])->orderBy('created_at');

        $active = (clone $base)->whereIn('kitchen_status', ['new', 'preparing', 'ready'])->get()->groupBy('kitchen_status');
        $done = (clone $base)->where('kitchen_status', 'delivered')->where('kitchen_updated_at', '>=', now()->subHours(3))
            ->reorder()->orderByDesc('kitchen_updated_at')->limit(12)->get();

        $settings = ShopSettings::where('tenant_id', $tenantId)->first();
        $suggest = [];
        foreach ($active['new'] ?? [] as $o) {
            $suggest[$o->id] = EtaCalculator::minutes($o, $settings);
        }

        return [
            'suggest' => $suggest,
            'busy'    => !empty($settings?->restaurant()['busy']),
            'busyExtra' => (int) ($settings?->restaurant()['busy_extra'] ?? 10),
            'tz'      => $settings?->timezone ?: 'America/La_Paz',
            'columns' => [
                'new'       => ['label' => 'Nuevos', 'orders' => $active['new'] ?? collect(), 'next' => 'preparing', 'action' => 'Empezar'],
                'preparing' => ['label' => 'Preparando', 'orders' => $active['preparing'] ?? collect(), 'next' => 'ready', 'action' => 'Listo'],
                'ready'     => ['label' => 'Listos', 'orders' => $active['ready'] ?? collect(), 'next' => 'delivered', 'action' => 'Entregado'],
            ],
            'done'    => $done,
            'newIds'  => ($active['new'] ?? collect())->pluck('id')->all(),
            'prevOf'  => ['preparing' => 'new', 'ready' => 'preparing', 'delivered' => 'ready'],
        ];
    }
}
