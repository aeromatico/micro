<?php namespace Aero\Shopify\Http\Controllers;

use Aero\Shopify\Classes\OrderProcessor;
use Aero\Shopify\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Webhooks de una tienda Shopify. La URL lleva el uuid de la tienda (no
 * adivinable) y la autenticidad la da la firma HMAC-SHA256 del cuerpo crudo
 * con el secreto de la app (header X-Shopify-Hmac-Sha256).
 */
class WebhookController extends Controller
{
    public function handle(Request $request, string $uuid, OrderProcessor $processor): JsonResponse
    {
        $store = Store::active()->where('uuid', $uuid)->first();
        if (!$store) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $secret = $store->clientSecret();
        $expected = $secret ? base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)) : null;
        $received = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        if (!$expected || !hash_equals($expected, $received)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $store->forceFill(['last_webhook_at' => now()])->save();

        $topic = (string) $request->header('X-Shopify-Topic', '');
        $shop = strtolower((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shop !== '' && $shop !== $store->shop_domain) {
            return response()->json(['error' => 'shop_mismatch'], 403);
        }

        if ($topic === 'orders/create') {
            $processor->process($store, (array) $request->json()->all());
        }

        // Otros topics se aceptan y se ignoran para que Shopify no los reintente.
        return response()->json(['ok' => true]);
    }
}
