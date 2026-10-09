<?php namespace Aero\Shopify\Classes;

use Aero\Shopify\Models\Store;
use Illuminate\Support\Facades\Http;

/** Cliente mínimo de la Admin GraphQL API de una tienda concreta. */
class ShopifyClient
{
    public const API_VERSION = '2025-10';

    public function __construct(protected Store $store)
    {
    }

    /** @throws \RuntimeException con el mensaje de Shopify si algo falla */
    public function graphql(string $query, array $variables = []): array
    {
        $token = $this->store->accessToken();
        if (!$token) {
            throw new \RuntimeException('La tienda no tiene token de Admin API configurado.');
        }

        $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])
            ->timeout(20)
            ->post("https://{$this->store->shop_domain}/admin/api/" . self::API_VERSION . '/graphql.json', [
                'query'     => $query,
                'variables' => $variables,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Shopify respondió HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        $json = $response->json();
        if (!empty($json['errors'])) {
            throw new \RuntimeException('Shopify: ' . json_encode($json['errors'], JSON_UNESCAPED_UNICODE));
        }

        return $json['data'] ?? [];
    }

    /** Prueba de conexión: devuelve el nombre de la tienda. */
    public function shopName(): string
    {
        return (string) ($this->graphql('{ shop { name } }')['shop']['name'] ?? '');
    }

    /**
     * Marca el pedido como pagado. Idempotente de hecho: si ya estaba pagado
     * Shopify devuelve un userError que se trata como éxito.
     */
    public function markOrderPaid(string $shopifyOrderId): void
    {
        $gid = str_starts_with($shopifyOrderId, 'gid://') ? $shopifyOrderId : "gid://shopify/Order/{$shopifyOrderId}";

        $data = $this->graphql(
            'mutation($input: OrderMarkAsPaidInput!) { orderMarkAsPaid(input: $input) { order { id } userErrors { field message } } }',
            ['input' => ['id' => $gid]]
        );

        $errors = $data['orderMarkAsPaid']['userErrors'] ?? [];
        if ($errors) {
            $message = implode('; ', array_column($errors, 'message'));
            if (stripos($message, 'already') !== false || stripos($message, 'ya ') === 0) {
                return;
            }
            throw new \RuntimeException($message);
        }
    }
}
