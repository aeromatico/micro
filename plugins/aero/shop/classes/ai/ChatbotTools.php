<?php namespace Aero\Shop\Classes\Ai;

use Aero\Shop\Models\Product;

/**
 * Handlers de las "AI tools" (categoría `shop`) que Aero.Shop registra en
 * `Aero\Chatbots\Classes\AiToolRegistry` vía el evento
 * `aero.chatbots.registerAiTools` (ver Plugin::bootChatbotsIntegration()).
 *
 * `$tenantId` SIEMPRE es el del bot que está respondiendo, resuelto por
 * ChatbotEngine — nunca algo que venga dentro de `$arguments`.
 */
class ChatbotTools
{
    protected const MAX_RESULTS = 20;

    public static function listProducts(array $arguments, int $tenantId): array
    {
        $query = Product::forTenant($tenantId)->active();

        if ($search = trim((string) ($arguments['query'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->limit(static::MAX_RESULTS)->get();

        if ($products->isEmpty()) {
            return ['products' => [], 'note' => 'No se encontraron productos activos que coincidan.'];
        }

        return [
            'products' => $products->map(fn (Product $product) => [
                'name'        => $product->name,
                'description' => $product->description,
                'price'       => $product->display_price,
                'price_range' => $product->has_price_range,
                'in_stock'    => $product->is_in_stock,
            ])->all(),
        ];
    }
}
