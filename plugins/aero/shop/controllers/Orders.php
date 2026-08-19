<?php namespace Aero\Shop\Controllers;

use Aero\Shop\Classes\InventoryService;
use Aero\Shop\Models\Order;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Db;
use Flash;

class Orders extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.shop.manage_orders'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shop', 'tienda', 'shop-pedidos');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    protected function transition(int $orderId, string $toStatus, callable $sideEffect = null)
    {
        $tenantId = $this->getCurrentTenantId();
        $order = Order::forTenant($tenantId)->findOrFail($orderId);
        $fromStatus = $order->status;
        $userId = BackendAuth::getUser()->id;

        Db::transaction(function () use ($order, $toStatus, $fromStatus, $userId, $sideEffect) {
            $order->status = $toStatus;
            $order->save();

            if ($sideEffect) {
                $sideEffect($order, $userId, $fromStatus);
            }

            $order->status_history()->create([
                'from_status'                => $fromStatus,
                'to_status'                  => $toStatus,
                'changed_by_backend_user_id' => $userId,
            ]);
        });

        return $order;
    }

    public function onMarkAsPaid($recordId = null)
    {
        $orderId = $recordId ?: post('order_id');
        // El stock ya se reservó al crear el pedido (checkout público, vía
        // InventoryService::reserveForOrderStrict) — aquí solo se confirma el pago.
        $this->transition((int) $orderId, 'paid', function (Order $order, int $userId) {
            $order->paid_at = now();
            $order->paid_confirmed_by_backend_user_id = $userId;
            $order->save();
        });

        Flash::success('Pedido marcado como pagado.');
        return $this->formRefresh();
    }

    public function onMarkAsFulfilled($recordId = null)
    {
        $orderId = $recordId ?: post('order_id');
        $this->transition((int) $orderId, 'fulfilled', function (Order $order) {
            $order->fulfilled_at = now();
            $order->save();
        });

        Flash::success('Pedido marcado como despachado/entregado.');
        return $this->formRefresh();
    }

    public function onCancelOrder($recordId = null)
    {
        $orderId = $recordId ?: post('order_id');
        $reason  = post('cancel_reason');

        $this->transition((int) $orderId, 'cancelled', function (Order $order, int $userId) use ($reason) {
            $order->cancelled_at = now();
            $order->cancel_reason = $reason;
            $order->save();

            // El stock siempre se reserva al crear el pedido (checkout público),
            // así que cualquier cancelación (venga del estado que venga) libera.
            (new InventoryService)->releaseForOrder($order, $userId);
        });

        Flash::success('Pedido cancelado.');
        return $this->formRefresh();
    }

    protected function formRefresh()
    {
        return [
            '#Form-' . $this->formGetWidget()->getId() => $this->formGetWidget()->render(),
        ];
    }
}
