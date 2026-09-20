<?php namespace Aero\Chat\Http\Controllers;

use Aero\Chat\Classes\ChargeSettler;
use Aero\Chat\Models\ChatEvent;
use Aero\Chat\Models\ChatOrder;
use Aero\Hello\Classes\ApiCredits;
use Aero\Hello\Classes\MessageComposer;
use Aero\Hello\Models\Account;
use Aero\Hello\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Ventas desde la conversación: el agente arma un pedido con productos de la
 * tienda del tenant y se crea un pedido REAL (mismo servicio que el checkout
 * web y la API), cuyo cobro y enlace se envían al cliente por el mismo chat.
 */
class ShopController extends Controller
{
    use \Aero\Chat\Classes\ValidatesJson;

    /** GET shop/products?q=&page= */
    public function products(Request $request)
    {
        if (!$this->available($this->tenantId($request))) {
            return $this->ok([], 200, ['total' => 0]);
        }

        $page = (new \Aero\Shop\Classes\CatalogService())->search($this->tenantId($request), $request->query('q'), null, 20);

        return $this->ok(array_map(fn ($p) => \Aero\Shop\Classes\CatalogService::present($p), $page->items()), 200,
            ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]);
    }

    /** GET conversations/{id}/shop — disponibilidad, medios de pago y pedidos de esta conversación. */
    public function show(Request $request, $id)
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return $err;
        }

        return $this->ok($this->state($request, $conv) + ['locations' => \Aero\Chat\Classes\SharedLocations::for($conv)]);
    }

    /**
     * POST conversations/{id}/shop/order
     * {items:[{product_id,variant_id?,quantity}], payment_gateway_id?, notes?, shipping?, customer?:{name,phone,email}, notify_on_paid?}
     */
    public function order(Request $request, $id)
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return $err;
        }

        $tenantId = $this->tenantId($request);
        if (!$this->available($tenantId)) {
            return $this->fail('shop_unavailable', 'La tienda no está activada para este espacio.', 422);
        }

        $data = $this->check($request, [
            'items' => 'required|array|min:1|max:50', 'items.*.product_id' => 'required|integer', 'items.*.variant_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1|max:999', 'payment_gateway_id' => 'nullable|integer', 'notes' => 'nullable|string|max:1000',
            'shipping' => 'nullable|array', 'shipping.latitude' => 'nullable|numeric|between:-90,90', 'shipping.longitude' => 'nullable|numeric|between:-180,180', 'shipping.location_label' => 'nullable|string|max:120', 'customer' => 'nullable|array', 'customer.name' => 'nullable|string|max:150',
            'customer.phone' => 'nullable|string|max:30', 'customer.email' => 'nullable|email|max:255', 'notify_on_paid' => 'nullable|boolean',
        ]);

        [$name, $phone] = $this->contactIdentity($conv);
        $name = $data['customer']['name'] ?? $name;
        [$first, $last] = array_pad(explode(' ', trim($name), 2), 2, null);

        try {
            $order = (new \Aero\Shop\Classes\OrderService())->create($tenantId, $data['items'], [
                'first_name' => $first ?: 'Cliente', 'last_name' => $last,
                'phone' => $data['customer']['phone'] ?? $phone, 'email' => $data['customer']['email'] ?? null,
            ], $data['payment_gateway_id'] ?? null, ['shipping' => $data['shipping'] ?? null, 'customer_notes' => $data['notes'] ?? null, 'source' => 'chat']);
        } catch (\Aero\Shop\Classes\Exceptions\InsufficientStockException $e) {
            return $this->fail('insufficient_stock', $e->getMessage(), 409);
        } catch (\RuntimeException $e) {
            return $this->fail('order_failed', $e->getMessage(), 422);
        }

        $me = $request->attributes->get('chat_user');
        $link = ChatOrder::create(['tenant_id' => $tenantId, 'conversation_id' => $conv->id, 'user_id' => $me->id, 'order_id' => $order->id,
            'notify_on_paid' => (bool) ($data['notify_on_paid'] ?? true)]);

        if (!$conv->assigned_to) {
            $conv->update(['assigned_to' => $me->id]);
        }

        $sent = $this->send($request, $conv, $order);
        $this->log($request, $conv, 'order', 'Pedido ' . $order->order_number . ' creado: ' . ChargeSettler::money($order->grand_total, $order->currency?->code) . ($sent === true ? '' : ' (no se pudo enviar al cliente)'),
            ['order_id' => $order->id]);

        if ($sent !== true) {
            // El pedido existe y se puede reenviar; se avisa sin perderlo.
            return $this->ok($this->state($request, $conv) + ['send_error' => $sent], 207);
        }

        return $this->ok($this->state($request, $conv), 201);
    }

    /**
     * POST conversations/{id}/shop/products/{productId}/card
     * Presenta un producto en el chat: foto, precio, resumen y enlace a su página en la tienda.
     */
    public function card(Request $request, $id, $productId)
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return $err;
        }

        $tenantId = $this->tenantId($request);
        if (!$this->available($tenantId)) {
            return $this->fail('shop_unavailable', 'La tienda no está activada para este espacio.', 422);
        }

        $product = (new \Aero\Shop\Classes\CatalogService())->find($tenantId, (int) $productId);
        $tenant = \Aero\Sites\Models\Tenant::find($tenantId);
        if (!$product || !$tenant) {
            return $this->fail('not_found', 'Producto no encontrado.', 404);
        }

        $p = \Aero\Shop\Classes\CatalogService::present($product);
        $code = \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenantId)->first()?->base_currency?->code;
        $url = 'https://' . $tenant->primary_domain . '/tienda/producto/' . $p['slug'];

        $lines = ['*' . $p['name'] . '*', ($p['has_price_range'] ? 'Desde ' : '') . ChargeSettler::money($p['price'], $code)
            . (!$p['in_stock'] ? ' · Agotado' : '')];
        if ($p['description']) {
            $lines[] = trim(mb_substr($p['description'], 0, 200)) . (mb_strlen($p['description']) > 200 ? '…' : '');
        }
        $lines[] = "\nVer en la tienda: " . $url;

        $options = [];
        if ($p['image_url'] && $conv->account->can('media')) {
            $image = $p['image_url'];
            $options = ['media_url' => str_starts_with($image, '/') ? url($image) : $image, 'media_type' => 'image'];
        }

        try {
            $tx = ApiCredits::charge($tenantId);
            MessageComposer::sendToContact($conv->account, $conv->contact_id, implode("\n", $lines), $options + ['credit_transaction_id' => $tx]);
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->fail('insufficient_credits', $e->getMessage(), 402);
        } catch (\Throwable $e) {
            return $this->fail('send_failed', $e->getMessage(), 422);
        }

        $this->log($request, $conv, 'product', 'Tarjeta de producto enviada: ' . $p['name'], ['product_id' => $p['id']]);

        return $this->ok(['sent' => true, 'url' => $url], 202);
    }

    /** POST conversations/{id}/shop/orders/{orderId}/resend */
    public function resend(Request $request, $id, $orderId)
    {
        [$conv, $err, $order] = $this->linked($request, $id, $orderId);
        if ($err) {
            return $err;
        }

        $sent = $this->send($request, $conv, $order);

        return $sent === true ? $this->ok($this->state($request, $conv)) : $this->fail('send_failed', $sent, 422);
    }

    /** POST conversations/{id}/shop/orders/{orderId}/cancel */
    public function cancel(Request $request, $id, $orderId)
    {
        [$conv, $err, $order] = $this->linked($request, $id, $orderId);
        if ($err) {
            return $err;
        }

        try {
            (new \Aero\Shop\Classes\OrderService())->cancel($order, 'Cancelado desde el chat');
        } catch (\Aero\Shop\Classes\Exceptions\OrderException $e) {
            return $this->fail('cannot_cancel', $e->getMessage(), 409);
        }

        $this->log($request, $conv, 'order', 'Pedido ' . $order->order_number . ' cancelado', ['order_id' => $order->id]);

        return $this->ok($this->state($request, $conv));
    }

    // ------------------------------------------------------------------

    /** Devuelve true, o el texto del error si no se pudo enviar. */
    protected function send(Request $request, Conversation $conv, $order)
    {
        $order->loadMissing(['items', 'currency', 'payment_gateway']);
        $money = fn ($n) => ChargeSettler::money($n, $order->currency?->code);

        $lines = $order->items->map(fn ($i) => '• ' . $i->quantity . ' × ' . $i->product_name_snapshot . ($i->variant_label_snapshot ? ' (' . $i->variant_label_snapshot . ')' : '') . ' — ' . $money($i->line_total))->implode("\n");
        $body = 'Pedido ' . $order->order_number . "\n" . $lines . "\nTotal: " . $money($order->grand_total);

        $options = [];
        $qr = $order->payment_reference && class_exists(\Aero\Pay\Models\QrCode::class)
            ? \Aero\Pay\Models\QrCode::where('internal_reference', $order->payment_reference)->first() : null;

        if ($qr && $qr->qr_image) {
            $body .= "\n\nPaga escaneando este QR desde tu app bancaria.";
            $options = ['media_url' => url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image'), 'media_type' => 'image'];
        } elseif ($order->payment_gateway?->instructions) {
            $body .= "\n\n" . trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $order->payment_gateway->instructions))));
        }
        $body .= "\n\nVer tu pedido: " . \Aero\Shop\Classes\OrderService::publicUrl($order);

        try {
            $tx = ApiCredits::charge($this->tenantId($request));
            MessageComposer::sendToContact($conv->account, $conv->contact_id, $body, $options + ['credit_transaction_id' => $tx]);
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return 'No hay créditos para enviar el mensaje. ' . $e->getMessage();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return true;
    }

    protected function state(Request $request, Conversation $conv): array
    {
        $tenantId = $this->tenantId($request);
        $available = $this->available($tenantId);
        $out = ['available' => $available];

        if (!$available) {
            return $out;
        }

        $settings = \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenantId)->first();
        $currency = $settings?->base_currency;
        [$name, $phone] = $this->contactIdentity($conv);

        $out += [
            'currency' => ['code' => $currency?->code, 'symbol' => $currency?->symbol],
            'customer' => ['name' => $name, 'phone' => $phone],
            'gateways' => \Aero\Shop\Models\PaymentGateway::forTenant($tenantId)->active()->orderBy('sort_order')->get()
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'driver' => $g->driver])->all(),
            'orders' => [],
        ];

        $links = ChatOrder::where('conversation_id', $conv->id)->latest('id')->take(15)->get();
        $orders = \Aero\Shop\Models\Order::with(['items', 'currency'])->whereIn('id', $links->pluck('order_id'))->get()->keyBy('id');
        foreach ($links as $l) {
            if ($o = $orders->get($l->order_id)) {
                $out['orders'][] = ['id' => $o->id, 'number' => $o->order_number, 'status' => $o->status, 'total' => (float) $o->grand_total,
                    'currency' => $o->currency?->code, 'items' => $o->items->sum('quantity'), 'created_at' => optional($o->created_at)->toIso8601String()];
            }
        }

        return $out;
    }

    /** Nombre y teléfono del cliente según el CRM (si hay) o el contacto del chat. */
    protected function contactIdentity(Conversation $conv): array
    {
        $contact = $conv->contact;
        $external = $contact?->identities()->where('platform', 'whatsapp')->value('external_id');
        $phone = $external ? '+' . preg_replace('/\D+/', '', $external) : null;

        $name = null;
        if (class_exists(\Aero\Crm\Models\Contact::class) && $contact) {
            $name = \Aero\Crm\Models\Contact::where('hello_contact_id', $contact->id)->first()?->full_name;
        }
        if (!$name && $contact && !$contact->hasPlaceholderName()) {
            $name = $contact->name;
        }

        return [$name ?: 'Cliente', $phone];
    }

    protected function available(int $tenantId): bool
    {
        return class_exists(\Aero\Shop\Classes\OrderService::class)
            && \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenantId)->where('is_enabled', true)->exists();
    }

    /** @return array [Conversation|null, JsonResponse|null, Order|null] */
    protected function linked(Request $request, $id, $orderId): array
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return [null, $err, null];
        }

        $link = ChatOrder::where('conversation_id', $conv->id)->where('order_id', $orderId)->first();
        $order = $link ? \Aero\Shop\Models\Order::forTenant($this->tenantId($request))->find($orderId) : null;

        return $order ? [$conv, null, $order] : [null, $this->fail('not_found', 'Pedido no encontrado en esta conversación.', 404), null];
    }

    protected function conversation(Request $request, $id): array
    {
        $ids = Account::forTenant($this->tenantId($request))->pluck('id');
        $conv = Conversation::whereIn('account_id', $ids)->with(['account', 'contact'])->find($id);

        return [$conv, $conv ? null : $this->fail('not_found', 'No encontrado.', 404)];
    }

    protected function log(Request $request, Conversation $c, string $type, string $body, array $data = []): void
    {
        ChatEvent::create(['tenant_id' => $this->tenantId($request), 'conversation_id' => $c->id, 'user_id' => $request->attributes->get('chat_user')->id, 'type' => $type, 'body' => $body, 'data' => $data ?: null]);
    }

    protected function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenant_id');
    }

    protected function ok($data, int $status = 200, array $meta = [])
    {
        return response()->json($meta ? ['data' => $data, 'meta' => $meta] : ['data' => $data], $status);
    }

    protected function fail(string $code, string $message, int $status)
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
