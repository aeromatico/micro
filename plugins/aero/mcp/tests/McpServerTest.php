<?php namespace Aero\Mcp\Tests;

use Aero\Api\Models\ApiKey;
use Aero\Chatbots\Classes\AiToolRegistry;
use Aero\Mcp\Classes\McpServer;
use Event;
use PluginTestCase;

/**
 * El servidor MCP solo expone lo que la key tiene permitido, nunca toma el
 * tenant de los argumentos y no revela tools ajenas ni errores internos.
 */
class McpServerTest extends PluginTestCase
{
    protected function registerFakeTools(): void
    {
        Event::listen('aero.chatbots.registerAiTools', function ($tenantId) {
            return [
                'crm.contacts.search' => [
                    'description' => 'Busca contactos.',
                    'category' => 'crm',
                    'parameters' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]],
                    'handler' => function (array $args, int $tenantId) {
                        return ['tenant' => $tenantId, 'q' => $args['q'] ?? null];
                    },
                ],
                'shop.orders.create' => [
                    'description' => 'Crea pedidos.',
                    'category' => 'shop',
                    'handler' => fn () => ['ok' => true],
                ],
                'boom.tool' => [
                    'description' => 'Siempre falla.',
                    'category' => null,
                    'handler' => function () { throw new \RuntimeException('secreto interno'); },
                ],
            ];
        });
    }

    protected function key(array $scopes): ApiKey
    {
        $key = new ApiKey();
        $key->scopes = $scopes;

        return $key;
    }

    protected function rpc(McpServer $server, string $method, array $params = []): ?array
    {
        return $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }

    public function testSinScopesNoHayToolsNiEjecucion(): void
    {
        $this->registerFakeTools();
        $server = new McpServer($this->key(['mcp.use']), 7);

        $this->assertSame([], $this->rpc($server, 'tools/list')['result']['tools']);

        $call = $this->rpc($server, 'tools/call', ['name' => 'crm.contacts.search', 'arguments' => ['q' => 'ana']]);
        $this->assertSame(-32602, $call['error']['code']);
    }

    public function testSoloVeLasToolsConScope(): void
    {
        $this->registerFakeTools();
        $server = new McpServer($this->key(['mcp.use', 'mcp.tool.crm.contacts.search']), 7);

        $names = array_column($this->rpc($server, 'tools/list')['result']['tools'], 'name');
        $this->assertSame(['crm.contacts.search'], $names);

        $denied = $this->rpc($server, 'tools/call', ['name' => 'shop.orders.create']);
        $this->assertSame(-32602, $denied['error']['code'], 'una tool sin scope responde como inexistente');
    }

    public function testComodinDeToolsCubreTodas(): void
    {
        $this->registerFakeTools();
        $server = new McpServer($this->key(['mcp.tool.*']), 7);

        $this->assertCount(3, $this->rpc($server, 'tools/list')['result']['tools']);
    }

    public function testElTenantVieneDeLaKeyNoDeLosArgumentos(): void
    {
        $this->registerFakeTools();
        $server = new McpServer($this->key(['mcp.tool.crm.contacts.search']), 7);

        $res = $this->rpc($server, 'tools/call', [
            'name' => 'crm.contacts.search',
            'arguments' => ['q' => 'ana', 'tenant_id' => 999],
        ]);

        $payload = json_decode($res['result']['content'][0]['text'], true);
        $this->assertSame(7, $payload['tenant']);
        $this->assertFalse($res['result']['isError']);
    }

    public function testKeySinTenantNoEjecutaNada(): void
    {
        $this->registerFakeTools();
        $server = new McpServer($this->key(['*']), null);

        $this->assertSame([], $this->rpc($server, 'tools/list')['result']['tools']);
        $this->assertSame(-32002, $this->rpc($server, 'tools/call', ['name' => 'crm.contacts.search'])['error']['code']);
    }

    public function testErrorDeToolNoFiltraDetalles(): void
    {
        $this->registerFakeTools();
        $server = new McpServer($this->key(['mcp.tool.boom.tool']), 7);

        $res = $this->rpc($server, 'tools/call', ['name' => 'boom.tool']);

        $this->assertTrue($res['result']['isError']);
        $this->assertStringNotContainsString('secreto', $res['result']['content'][0]['text']);
    }

    public function testNotificacionNoTieneRespuestaYMetodoInvalido(): void
    {
        $server = new McpServer($this->key(['mcp.use']), 7);

        $this->assertNull($server->handle(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
        $this->assertSame(-32601, $this->rpc($server, 'metodo/inexistente')['error']['code']);
    }

    public function testInitializeDeclaraTools(): void
    {
        $server = new McpServer($this->key(['mcp.use']), 7);
        $res = $this->rpc($server, 'initialize');

        $this->assertSame(McpServer::PROTOCOL_VERSION, $res['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $res['result']['capabilities']);
    }
}
