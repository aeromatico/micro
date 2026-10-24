<?php namespace Aero\Producer\Tests;

use Aero\Producer\Classes\Production;
use Aero\Producer\Models\Credential;
use Aero\Producer\Models\Order;
use Aero\Producer\Models\Style;
use Illuminate\Support\Facades\Http;
use PluginTestCase;

class ProductionTest extends PluginTestCase
{
    protected function order(string $slug, array $attrs = []): Order
    {
        $style = Style::where('slug', $slug)->firstOrFail();
        Credential::create(['label' => 'HeyGen', 'provider' => 'heygen', 'api_key' => 'sk_test_123', 'is_active' => true]);

        $order = Order::create(['tenant_id' => 1, 'style_id' => $style->id, 'name' => 'P']);
        $order->forceFill(array_merge(['status' => 'producing', 'provider_ref' => 'vid1'], $attrs))->save();

        return $order;
    }

    public function testOnlyPresenterOrdersAreProducedAutomatically(): void
    {
        $this->assertTrue(Production::canProduce($this->order('presentador-calle', ['status' => 'queued'])));
        $this->assertFalse(Production::canProduce($this->order('explicativo-palitos', ['status' => 'queued'])));
        $this->assertFalse(Production::canProduce($this->order('presentador-calle', ['status' => 'draft'])));
    }

    public function testPollKeepsWaitingWhileTheProviderRenders(): void
    {
        Http::fake(['api.heygen.com/v3/videos/vid1' => Http::response(['data' => ['status' => 'processing']])]);
        $order = $this->order('presentador-calle');

        $this->assertFalse(Production::poll($order));
        $this->assertSame('producing', $order->fresh()->status);
    }

    public function testProviderFailureReleasesTheVideoSoItCanBeRetried(): void
    {
        Http::fake(['api.heygen.com/v3/videos/vid1' => Http::response(['data' => ['status' => 'failed', 'failure_message' => 'Imagen sin rostro']])]);
        $order = $this->order('presentador-calle');

        $this->assertTrue(Production::poll($order));
        $fresh = $order->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('Imagen sin rostro', $fresh->error_message);
        $this->assertNull($fresh->provider_ref);
    }

    public function testNetworkErrorKeepsTheProviderRefToAvoidPayingTwice(): void
    {
        $order = $this->order('presentador-calle');
        Production::fail($order, 'Timeout');

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('vid1', $order->fresh()->provider_ref);
    }

    public function testApiKeyIsStoredEncrypted(): void
    {
        $credential = Credential::create(['label' => 'H', 'provider' => 'heygen', 'api_key' => 'sk_plain_abc', 'is_active' => true]);

        $this->assertNotSame('sk_plain_abc', $credential->api_key_enc);
        $this->assertSame('sk_plain_abc', $credential->plainKey());
    }
}
