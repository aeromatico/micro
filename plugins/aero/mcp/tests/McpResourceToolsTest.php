<?php namespace Aero\Mcp\Tests;

use Aero\Mcp\Classes\McpResourceTools;
use Aero\Mcp\Classes\ToolError;
use Illuminate\Database\Eloquent\Model;
use Schema;
use PluginTestCase;

class FakeContact extends Model
{
    protected $table = 'mcp_test_contacts';
    protected $guarded = [];
}

class FakeNote extends Model
{
    protected $table = 'mcp_test_notes';
    protected $guarded = [];
    public $timestamps = false;
}

/**
 * El motor impone aislamiento por tenant, lista blanca de campos y escritura
 * solo donde se declara, sin depender de cada plugin.
 */
class McpResourceToolsTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Schema::create('mcp_test_contacts', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('tenant_id')->nullable();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('api_token')->nullable();
            $t->string('internal')->nullable();
            $t->timestamps();
        });
        Schema::create('mcp_test_notes', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('contact_id');
            $t->string('body');
        });

        FakeContact::create(['tenant_id' => 1, 'name' => 'Ana', 'email' => 'ana@x.bo', 'api_token' => 'SECRETO', 'internal' => 'i1']);
        FakeContact::create(['tenant_id' => 2, 'name' => 'Beto', 'email' => 'beto@x.bo', 'api_token' => 'OTRO', 'internal' => 'i2']);
        FakeNote::create(['contact_id' => 1, 'body' => 'nota de ana']);
        FakeNote::create(['contact_id' => 2, 'body' => 'nota de beto']);
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('mcp_test_notes');
        Schema::dropIfExists('mcp_test_contacts');
        parent::tearDown();
    }

    protected function def(array $extra = []): array
    {
        return McpResourceTools::normalize('test_contacts', array_merge([
            'model'   => FakeContact::class,
            'label'   => 'contactos',
            'fields'  => 'id,name,email,api_token',   // api_token debe caer por la lista negra
            'search'  => 'name,email',
            'filters' => 'email',
        ], $extra));
    }

    public function testListaSoloElTenantYNuncaCamposSensibles(): void
    {
        $res = McpResourceTools::list($this->def(), [], 1);

        $this->assertSame(1, $res['total']);
        $this->assertSame('Ana', $res['data'][0]['name']);
        $this->assertArrayNotHasKey('api_token', $res['data'][0]);
        $this->assertArrayNotHasKey('internal', $res['data'][0], 'un campo no declarado nunca sale');
    }

    public function testGetDeOtroTenantEsNoEncontrado(): void
    {
        $this->expectException(ToolError::class);

        McpResourceTools::get($this->def(), ['id' => 2], 1);
    }

    public function testBusquedaYFiltrosRespetanLaListaBlanca(): void
    {
        $def = $this->def();

        $this->assertSame(1, McpResourceTools::list($def, ['query' => 'ana'], 1)['total']);
        $this->assertSame(0, McpResourceTools::list($def, ['query' => 'beto'], 1)['total'], 'no cruza tenants');
        // un filtro no declarado se ignora (no filtra, no rompe)
        $this->assertSame(1, McpResourceTools::list($def, ['filters' => ['internal' => 'zzz']], 1)['total']);
        $this->assertSame(0, McpResourceTools::list($def, ['filters' => ['email' => 'otro@x.bo']], 1)['total']);
    }

    public function testLimiteSeAcota(): void
    {
        $this->assertSame(McpResourceTools::MAX_LIMIT, McpResourceTools::list($this->def(), ['limit' => 100000], 1)['per_page']);
        $this->assertSame(1, McpResourceTools::list($this->def(), ['limit' => -5], 1)['per_page']);
    }

    public function testSinWritableNoHayToolsDeEscritura(): void
    {
        $tools = McpResourceTools::toolsFor($this->def());

        $this->assertSame(['test_contacts_list', 'test_contacts_get'], array_keys($tools));
        $this->assertFalse($tools['test_contacts_list']['write']);
    }

    public function testCrearFuerzaElTenantYDescartaCamposNoEscribibles(): void
    {
        $def = $this->def(['writable' => 'name,email,api_token,tenant_id,id']);

        $this->assertSame(['name', 'email'], $def['writable'], 'sensibles, tenant e id nunca son escribibles');

        $row = McpResourceTools::create($def, ['name' => 'Carla', 'email' => 'c@x.bo', 'tenant_id' => 2, 'internal' => 'hack'], 1);

        $this->assertSame('Carla', $row['name']);
        $stored = FakeContact::find($row['id']);
        $this->assertSame(1, (int) $stored->tenant_id);
        $this->assertNull($stored->internal);
    }

    public function testActualizarOtroTenantFalla(): void
    {
        $def = $this->def(['writable' => 'name']);

        $this->expectException(ToolError::class);
        try {
            McpResourceTools::update($def, ['id' => 2, 'name' => 'Pwned'], 1);
        } finally {
            $this->assertSame('Beto', FakeContact::find(2)->name);
        }
    }

    public function testRecursoHijoSeAislaPorElPadreYNoEsEscribible(): void
    {
        $def = McpResourceTools::normalize('test_notes', [
            'model' => FakeNote::class,
            'tenant_via' => ['contact_id', FakeContact::class],
            'fields' => 'id,contact_id,body',
            'writable' => 'body',
        ]);

        $this->assertSame([], $def['writable']);

        $res = McpResourceTools::list($def, [], 1);
        $this->assertSame(['nota de ana'], array_column($res['data'], 'body'));

        $this->expectException(ToolError::class);
        McpResourceTools::get($def, ['id' => 2], 1);
    }

    public function testTenantInvalidoFallaCerrado(): void
    {
        $this->expectException(ToolError::class);

        McpResourceTools::list($this->def(), [], 0);
    }

    public function testModeloInexistenteSeOmite(): void
    {
        $this->assertNull(McpResourceTools::normalize('x', ['model' => 'Aero\\NoExiste\\Models\\Nada']));
    }
}
