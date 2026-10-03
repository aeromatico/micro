<?php namespace Aero\Shop\Controllers;

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

        Db::transaction(function () use ($order, $to, $userId) {
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

    protected function boardData(?int $tenantId): array
    {
        $base = Order::forTenant($tenantId)->where('status', '!=', 'cancelled')
            ->with(['items.product', 'customer', 'currency'])->orderBy('created_at');

        $active = (clone $base)->whereIn('kitchen_status', ['new', 'preparing', 'ready'])->get()->groupBy('kitchen_status');
        $done = (clone $base)->where('kitchen_status', 'delivered')->where('kitchen_updated_at', '>=', now()->subHours(3))
            ->reorder()->orderByDesc('kitchen_updated_at')->limit(12)->get();

        return [
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
