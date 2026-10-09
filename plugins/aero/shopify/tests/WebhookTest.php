<?php namespace Aero\Shopify\Tests;

use Aero\Shopify\Classes\OrderProcessor;
use Aero\Shopify\Models\Order;
use Aero\Shopify\Models\Store;
use PluginTestCase;

class WebhookTest extends PluginTestCase
{
    protected function makeStore(array $attrs = []): Store
    {
        $store = new Store(array_merge([
            'tenant_id' => 7, 'name' => 'Demo', 'shop_domain' => 'demo-' . uniqid() . '.myshopify.com',
            'gateway_names' => 'QR Bolivia, Pago QR', 'is_active' => true,
        ], $attrs));
        $store->setSecrets('shpat_test', 's3cret');
        $store->save();

        return $store;
    }

    protected function payload(array $extra = []): array
    {
        return array_merge([
            'id' => 1001, 'name' => '#1001', 'email' => 'Cliente@Example.com', 'total_price' => '150.00',
            'currency' => 'BOB', 'payment_gateway_names' => ['QR Bolivia'],
        ], $extra);
    }

    protected function signedPost(Store $store, array $payload, ?string $secret = 's3cret')
    {
        $body = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return $this->call('POST', "/api/v1/shopify/webhooks/{$store->uuid}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $store->shop_domain,
        ], $body);
    }

    public function testSecretsAreEncryptedAtRest(): void
    {
        $store = $this->makeStore();
        $raw = \DB::table('aero_shopify_stores')->where('id', $store->id)->first();

        $this->assertStringNotContainsString('shpat_test', $raw->access_token);
        $this->assertSame('shpat_test', $store->fresh()->accessToken());
        $this->assertSame('s3cret', $store->fresh()->clientSecret());
    }

    public function testGatewayMatchingIsCaseInsensitive(): void
    {
        $store = $this->makeStore();

        $this->assertTrue($store->matchesGateway(['pago qr']));
        $this->assertFalse($store->matchesGateway(['manual', 'paypal']));
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $store = $this->makeStore();

        $this->signedPost($store, $this->payload(), 'otro-secreto')->assertStatus(401);
        $this->assertSame(0, Order::count());
    }

    public function testValidSignatureCreatesOrderOnceAndIsIdempotent(): void
    {
        $store = $this->makeStore();

        $this->signedPost($store, $this->payload())->assertOk();
        $this->signedPost($store, $this->payload())->assertOk();

        $this->assertSame(1, Order::where('store_id', $store->id)->count());
        $link = Order::first();
        $this->assertSame(7, (int) $link->tenant_id);
        $this->assertSame('cliente@example.com', $link->customer_email);
        // Sin cuenta bancaria asignada el QR no se puede emitir: queda en error visible, no se pierde.
        $this->assertSame('error', $link->status);
    }

    public function testOtherGatewayIsIgnored(): void
    {
        $store = $this->makeStore();

        $this->assertNull(app(OrderProcessor::class)->process($store, $this->payload(['payment_gateway_names' => ['Shopify Payments']])));
        $this->assertSame(0, Order::count());
    }

    public function testInactiveOrUnknownStoreIs404(): void
    {
        $store = $this->makeStore(['is_active' => false]);

        $this->signedPost($store, $this->payload())->assertStatus(404);
    }

    public function testLookupDoesNotLeakOtherEmailsOrders(): void
    {
        $store = $this->makeStore();
        $this->signedPost($store, $this->payload())->assertOk();

        $this->post("/shopify/pagar/{$store->uuid}", ['order' => '1001', 'email' => 'otro@example.com'])->assertStatus(404);
        $this->post("/shopify/pagar/{$store->uuid}", ['order' => '1001', 'email' => 'cliente@example.com'])->assertRedirect();
    }

    public function testBankAccountFromAnotherTenantIsNotAccepted(): void
    {
        $store = $this->makeStore();
        $store->bank_account_id = 999;

        $this->assertNull($store->bankAccount());
    }
}
