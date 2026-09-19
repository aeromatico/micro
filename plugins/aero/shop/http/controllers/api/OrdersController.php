<?php namespace Aero\Shop\Http\Controllers\Api;

use Aero\Shop\Classes\Api\Presenter;
use Aero\Shop\Classes\Exceptions\InsufficientStockException;
use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Classes\OrderService;
use Aero\Shop\Models\Order;
use Illuminate\Http\Request;

class OrdersController extends ApiController
{
    /** GET /api/v1/shop/orders?status=&phone=&per_page= */
    public function index(Request $request)
    {
        $q = Order::forTenant($this->tenantId($request))->with(['items', 'customer', 'currency', 'payment_gateway'])->latest('id');

        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($phone = $request->query('phone')) {
            $q->whereHas('customer', fn ($c) => $c->where('phone', $phone));
        }

        return $this->paged($q->paginate(min(100, max(1, (int) $request->query('per_page', 20)))), fn ($o) => Presenter::order($o));
    }

    /** GET /api/v1/shop/orders/{id|number} */
    public function show(Request $request, $ref)
    {
        $order = $this->find($request, $ref);

        return $order ? $this->data(Presenter::order($order)) : $this->error('not_found', 'Pedido no encontrado.', 404);
    }

    /**
     * POST /api/v1/shop/orders
     * {customer:{first_name,last_name?,phone?,email?}, items:[{product_id,variant_id?,quantity}],
     *  payment_gateway_id?, shipping?:{address_line1,city,...}, notes?}
     */
    public function store(Request $request)
    {
        $data = $this->validated($request, [
            'customer'                  => 'required|array',
            'customer.first_name'       => 'required|string|max:100',
            'customer.last_name'        => 'nullable|string|max:100',
            'customer.phone'            => 'required_without:customer.email|nullable|string|max:30',
            'customer.email'            => 'required_without:customer.phone|nullable|email|max:255',
            'items'                     => 'required|array|min:1|max:50',
            'items.*.product_id'        => 'required|integer',
            'items.*.variant_id'        => 'nullable|integer',
            'items.*.quantity'          => 'required|integer|min:1|max:999',
            'payment_gateway_id'        => 'nullable|integer',
            'shipping'                  => 'nullable|array',
            'notes'                     => 'nullable|string|max:1000',
        ]);
        if (!is_array($data)) {
            return $data;
        }

        try {
            $order = (new OrderService())->create($this->tenantId($request), $data['items'], $data['customer'], $data['payment_gateway_id'] ?? null, [
                'shipping' => $data['shipping'] ?? null, 'customer_notes' => $data['notes'] ?? null, 'source' => 'api',
            ]);
        } catch (InsufficientStockException $e) {
            return $this->error('insufficient_stock', $e->getMessage(), 409);
        } catch (OrderException | \RuntimeException $e) {
            return $this->error('order_failed', $e->getMessage(), 422);
        }

        return $this->data(Presenter::order($order->load(['items', 'customer', 'currency', 'payment_gateway'])), 201);
    }

    /** POST /api/v1/shop/orders/{id|number}/cancel {reason?} */
    public function cancel(Request $request, $ref)
    {
        $order = $this->find($request, $ref);
        if (!$order) {
            return $this->error('not_found', 'Pedido no encontrado.', 404);
        }

        try {
            $order = (new OrderService())->cancel($order, $request->input('reason'));
        } catch (OrderException $e) {
            return $this->error('cannot_cancel', $e->getMessage(), 409);
        }

        return $this->data(Presenter::order($order->load(['items', 'customer', 'currency', 'payment_gateway'])));
    }

    protected function find(Request $request, $ref): ?Order
    {
        return Order::forTenant($this->tenantId($request))->with(['items', 'customer', 'currency', 'payment_gateway'])
            ->where(fn ($q) => $q->where('order_number', $ref)->orWhere('id', ctype_digit((string) $ref) ? (int) $ref : 0))->first();
    }
}
