<?php namespace Aero\Shopify\Http\Controllers;

use Aero\Shopify\Models\Order;
use Aero\Shopify\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Páginas públicas para el comprador: buscar su pedido (número + correo) y
 * ver/pagar el QR. El acceso al QR es por token aleatorio de 40 caracteres.
 */
class PayPageController extends Controller
{
    public function lookupForm(string $uuid)
    {
        $store = Store::active()->where('uuid', $uuid)->firstOrFail();

        return response(view('aeroshopify::lookup', ['store' => $store, 'error' => null]));
    }

    public function lookup(Request $request, string $uuid)
    {
        $store = Store::active()->where('uuid', $uuid)->firstOrFail();

        $name = ltrim(trim((string) $request->input('order')), '#');
        $email = mb_strtolower(trim((string) $request->input('email')));

        $link = ($name !== '' && $email !== '')
            ? Order::where('store_id', $store->id)
                ->whereIn('order_name', [$name, '#' . $name])
                ->where('customer_email', $email)
                ->latest('id')->first()
            : null;

        if (!$link) {
            // Mismo mensaje exista o no el pedido: no se filtra qué pedidos hay.
            return response(view('aeroshopify::lookup', ['store' => $store, 'error' => 'No encontramos un pedido con esos datos.']), 404);
        }

        return redirect($link->payUrl());
    }

    public function show(string $token)
    {
        $link = Order::with('store')->where('token', $token)->firstOrFail();

        return response(view('aeroshopify::pay', [
            'link'  => $link,
            'store' => $link->store,
            'qr'    => $link->qrCode(),
        ]));
    }

    public function status(string $token): JsonResponse
    {
        $link = Order::where('token', $token)->firstOrFail();

        return response()->json(['status' => $link->status])->header('Cache-Control', 'no-store');
    }
}
