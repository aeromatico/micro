<?php namespace Aero\Connector\Tests;

use Aero\Connector\Classes\AiGateway;
use Aero\Connector\Drivers\AiOpenAiCompatibleDriver;
use Aero\Connector\Models\Connector;
use Illuminate\Support\Facades\Http;
use PluginTestCase;

/**
 * Portkey y Cloudflare AI Gateway son proveedores del tipo ai_openai_compatible:
 * mismo protocolo, cabeceras y URL propias. Las llaves nunca salen del conector.
 */
class AiGatewayTest extends PluginTestCase
{
    protected function connector(string $hint, array $config = [], array $credentials = [], ?string $baseUrl = null): Connector
    {
        $c = new Connector();
        $c->name = 'gw';
        $c->provider_hint = $hint;
        $c->base_url = $baseUrl;
        $c->config = $config;
        $c->credentials = $credentials;
        $c->beforeValidate();

        return $c;
    }

    public function testTheProvidersResolveToTheOpenAiCompatibleType(): void
    {
        $this->assertSame('ai_openai_compatible', $this->connector('portkey')->type);
        $this->assertSame('ai_openai_compatible', $this->connector('cloudflare_ai_gateway')->type);
    }

    public function testPortkeyDefaultsToItsCloudUrl(): void
    {
        $c = $this->connector('portkey');

        $this->assertSame(AiGateway::PORTKEY_URL, $c->base_url);
    }

    public function testPortkeySendsItsHeaders(): void
    {
        $c = $this->connector('portkey', ['portkey_provider' => 'openai', 'portkey_config' => 'pc-123', 'portkey_metadata' => ['_user' => 'u1']], ['api_key' => 'sk-prov', 'secret' => 'pk-portkey']);

        $h = AiGateway::headers($c);

        $this->assertSame('openai', $h['x-portkey-provider']);
        $this->assertSame('pc-123', $h['x-portkey-config']);
        $this->assertSame('pk-portkey', $h['x-portkey-api-key']);
        $this->assertSame('{"_user":"u1"}', $h['x-portkey-metadata']);
        $this->assertArrayNotHasKey('Authorization', $h, 'el Bearer del proveedor lo pone el driver');
    }

    public function testCloudflareBuildsItsUrlFromAccountAndGateway(): void
    {
        $c = $this->connector('cloudflare_ai_gateway', ['account_id' => 'acc1', 'gateway_id' => 'gw1']);

        $this->assertSame('https://gateway.ai.cloudflare.com/v1/acc1/gw1/compat', AiGateway::baseUrl($c));
    }

    public function testCloudflareHeaders(): void
    {
        $c = $this->connector('cloudflare_ai_gateway', ['account_id' => 'a', 'gateway_id' => 'g', 'cf_cache_ttl' => 300, 'cf_skip_cache' => true, 'cf_max_attempts' => 2], ['secret' => 'gwtoken']);

        $h = AiGateway::headers($c);

        $this->assertSame('Bearer gwtoken', $h['cf-aig-authorization']);
        $this->assertSame('300', $h['cf-aig-cache-ttl']);
        $this->assertSame('true', $h['cf-aig-skip-cache']);
        $this->assertSame('2', $h['cf-aig-max-attempts']);
    }

    public function testPlainOpenAiConnectorsGetNoGatewayHeaders(): void
    {
        $c = $this->connector('openai', ['portkey_provider' => 'x'], ['secret' => 'zzz']);

        $this->assertSame([], AiGateway::headers($c));
        $this->assertNull(AiGateway::missing($c));
    }

    public function testMissingConfigurationIsReportedInsteadOfCallingOut(): void
    {
        Http::fake();

        $cf = $this->connector('cloudflare_ai_gateway');
        $r = (new AiOpenAiCompatibleDriver())->test($cf);

        $this->assertFalse($r->successful);
        $this->assertStringContainsString('account_id', (string) $r->error);
        Http::assertNothingSent();

        $pk = $this->connector('portkey');
        $this->assertStringContainsString('virtual key', (string) AiGateway::missing($pk));
    }

    public function testDriverSendsToTheGatewayWithItsHeadersAndModel(): void
    {
        Http::fake(['gateway.ai.cloudflare.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);
        $c = $this->connector('cloudflare_ai_gateway', ['account_id' => 'acc1', 'gateway_id' => 'gw1', 'model' => 'openai/gpt-4o-mini'], ['api_key' => 'sk-prov', 'secret' => 'gwtoken']);

        $r = (new AiOpenAiCompatibleDriver())->send($c, ['prompt' => 'hola']);

        $this->assertTrue($r->successful);
        Http::assertSent(function ($req) {
            return $req->url() === 'https://gateway.ai.cloudflare.com/v1/acc1/gw1/compat/chat/completions'
                && $req->hasHeader('Authorization', 'Bearer sk-prov')
                && $req->hasHeader('cf-aig-authorization', 'Bearer gwtoken')
                && $req['model'] === 'openai/gpt-4o-mini';
        });
    }

    public function testNoEmptyBearerWhenThereIsNoProviderKey(): void
    {
        Http::fake(['*' => Http::response(['choices' => []], 200)]);
        $c = $this->connector('portkey', ['portkey_virtual_key' => 'vk-1'], ['secret' => 'pk']);

        (new AiOpenAiCompatibleDriver())->send($c, ['prompt' => 'hola']);

        Http::assertSent(fn ($req) => !$req->hasHeader('Authorization') && $req->hasHeader('x-portkey-virtual-key', 'vk-1'));
    }
}
