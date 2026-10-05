<?php namespace Aero\Workflows\Tests;

use Aero\Workflows\Classes\AiTools;
use Aero\Workflows\Classes\Nodes\LocationCapture;
use Aero\Workflows\Classes\SafeUrl;
use Aero\Workflows\Classes\TemplateResolver;
use Aero\Workflows\Classes\Triggers;
use Aero\Workflows\Classes\WorkflowRunner;
use Aero\Workflows\Models\Workflow;
use PluginTestCase;

class WorkflowsTest extends PluginTestCase
{
    protected function graph(): array
    {
        return [
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.manual', 'data' => []],
                ['id' => 'c', 'type' => 'logic.condition', 'data' => ['left' => '{{ trigger.plan }}', 'op' => 'eq', 'right' => 'pro']],
                ['id' => 'yes', 'type' => 'action.respond', 'data' => ['value' => 'Hola {{ trigger.name }}, eres PRO']],
                ['id' => 'no', 'type' => 'action.respond', 'data' => ['value' => 'Plan básico']],
            ],
            'edges' => [
                ['source' => 't', 'target' => 'c'],
                ['source' => 'c', 'sourceHandle' => 'true', 'target' => 'yes'],
                ['source' => 'c', 'sourceHandle' => 'false', 'target' => 'no'],
            ],
        ];
    }

    protected function make(int $tenantId, string $slug, array $extra = []): Workflow
    {
        return Workflow::create($extra + [
            'tenant_id' => $tenantId, 'name' => $slug, 'slug' => $slug, 'is_active' => true,
            'trigger_type' => 'manual', 'graph' => json_encode($this->graph()),
        ]);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_workflows_workflows', 'aero_workflows_runs', 'aero_workflows_run_steps'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Workflows\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testRunnerFollowsConditionBranch(): void
    {
        $wf = $this->make(1, 'saludo');

        $pro = WorkflowRunner::start($wf, ['plan' => 'pro', 'name' => 'Ana'], 'manual', sync: true);
        $this->assertSame('ok', $pro->status);
        $this->assertSame('Hola Ana, eres PRO', $pro->decoded('result'));

        $basic = WorkflowRunner::start($wf, ['plan' => 'free'], 'manual', sync: true);
        $this->assertSame('Plan básico', $basic->decoded('result'));
        $this->assertSame(3, $basic->steps_count);
    }

    public function testRunnerFailsWithoutTriggerAndOnUnknownNode(): void
    {
        $wf = $this->make(1, 'roto', ['graph' => json_encode(['nodes' => [['id' => 'x', 'type' => 'action.respond']], 'edges' => []])]);
        $this->assertSame('error', WorkflowRunner::start($wf, [], 'manual', sync: true)->status);

        $wf2 = $this->make(1, 'roto2', ['graph' => json_encode(['nodes' => [['id' => 't', 'type' => 'trigger.manual'], ['id' => 'z', 'type' => 'nope.nada']], 'edges' => [['source' => 't', 'target' => 'z']]])]);
        $run = WorkflowRunner::start($wf2, [], 'manual', sync: true);
        $this->assertSame('error', $run->status);
        $this->assertStringContainsString('desconocido', $run->error);
    }

    public function testRunnerStopsLoops(): void
    {
        $wf = $this->make(1, 'bucle', ['graph' => json_encode([
            'nodes' => [['id' => 't', 'type' => 'trigger.manual'], ['id' => 's', 'type' => 'logic.set', 'data' => ['name' => 'a', 'value' => 1]]],
            'edges' => [['source' => 't', 'target' => 's'], ['source' => 's', 'target' => 's']],
        ])]);

        $run = WorkflowRunner::start($wf, [], 'manual', sync: true);
        $this->assertSame('error', $run->status);
        $this->assertStringContainsString('pasos', $run->error);
    }

    public function testTemplateResolverKeepsTypesAndNeverEvaluates(): void
    {
        $ctx = ['trigger' => ['n' => 5, 'tags' => ['a']], 'nodes' => []];

        $this->assertSame(5, TemplateResolver::resolve('{{ trigger.n }}', $ctx));
        $this->assertSame('n=5', TemplateResolver::resolve('n={{ trigger.n }}', $ctx));
        $this->assertNull(TemplateResolver::resolve('{{ trigger.nada }}', $ctx));
        $this->assertSame('{{ system("id") }}', TemplateResolver::resolve('{{ system("id") }}', $ctx));
    }

    public function testSafeUrlRejectsDangerousTargets(): void
    {
        foreach (['http://example.com', 'https://127.0.0.1/x', 'https://169.254.169.254/latest', 'https://user:pw@example.com', 'https://10.0.0.5', 'ftp://example.com'] as $url) {
            $this->assertFalse(SafeUrl::validate($url)[0], "Debió rechazar {$url}");
        }
    }

    public function testAiToolsAreOptInAndTenantIsolated(): void
    {
        $this->make(1, 'oculto');                                                       // sin expose_as_tool
        $this->make(1, 'cotizar-envio', ['expose_as_tool' => true, 'tool_description' => 'Cotiza']);
        $this->make(2, 'otro-tenant', ['expose_as_tool' => true]);
        $this->make(1, 'apagado', ['expose_as_tool' => true, 'is_active' => false]);

        $tools = AiTools::tools(1);

        $this->assertSame(['wf_cotizar_envio'], array_keys($tools));
        $this->assertSame('workflows', $tools['wf_cotizar_envio']['category']);
        $this->assertSame([], AiTools::tools(null));
        $this->assertSame(['wf_otro_tenant'], array_keys(AiTools::tools(2)));
    }

    public function testAiToolHandlerRunsWorkflowAndRejectsForeignTenant(): void
    {
        $this->make(1, 'saludar', ['expose_as_tool' => true]);
        $handler = AiTools::tools(1)['wf_saludar']['handler'];

        $ok = $handler(['plan' => 'pro', 'name' => 'Luis'], 1);
        $this->assertTrue($ok['ok']);
        $this->assertSame('Hola Luis, eres PRO', $ok['result']);

        // El mismo handler con otro tenant (bot ajeno) no encuentra el workflow.
        $this->assertArrayHasKey('error', $handler(['plan' => 'pro'], 2));
    }

    public function testEventTriggerFailsClosedAcrossTenants(): void
    {
        $wf = $this->make(1, 'on-event', ['trigger_type' => 'event', 'trigger_config' => json_encode(['event' => 'aero.test.algoPaso'])]);
        Triggers::flush();

        // Sin tenant en el evento: no dispara. Con otro tenant: tampoco.
        Triggers::handle('aero.test.algoPaso', [['x' => 1]]);
        Triggers::handle('aero.test.algoPaso', [['tenant_id' => 2]]);
        $this->assertSame(0, $wf->runs()->count());

        Triggers::handle('aero.test.algoPaso', [['tenant_id' => 1, 'x' => 1]]);
        $this->assertSame(1, $wf->runs()->count());

        // Los eventos del propio plugin nunca disparan nada (anti-recursión).
        Triggers::handle('aero.workflows.runFinished', [['tenant_id' => 1]]);
        $this->assertSame(1, $wf->runs()->count());
    }

    public function testWebhookRequiresSecretAndValidSignature(): void
    {
        $noSecret = $this->make(1, 'hook-sin-secreto', ['trigger_type' => 'webhook', 'trigger_config' => json_encode([])]);
        $this->call('POST', "workflows/hook/{$noSecret->id}", [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"plan":"pro"}')->assertStatus(404);

        $wf = $this->make(1, 'hook', ['trigger_type' => 'webhook', 'trigger_config' => json_encode(['secret' => 's3cr3t'])]);
        $body = '{"plan":"pro","name":"Eva"}';
        $server = ['CONTENT_TYPE' => 'application/json'];

        $this->call('POST', "workflows/hook/{$wf->id}", [], [], [], $server, $body)->assertStatus(401);

        $sig = 'sha256=' . hash_hmac('sha256', $body, 's3cr3t');
        $this->call('POST', "workflows/hook/{$wf->id}", [], [], [], $server + ['HTTP_X_SIGNATURE' => $sig], $body)->assertStatus(202);
        $this->assertSame(1, $wf->runs()->count());

        $this->call('POST', "workflows/hook/{$wf->id}", [], [], [], $server + ['HTTP_X_WEBHOOK_TOKEN' => 'mal'], $body)->assertStatus(401);
    }

    public static function locationProvider(): array
    {
        return [
            'whatsapp nativo'        => ["📍 (-16.500123, -68.150456)\nCasa de Ana Av. Arce 123", -16.500123, -68.150456, 'texto', 'Casa de Ana Av. Arce 123'],
            'par escrito'            => ['-16.5, -68.15', -16.5, -68.15, 'texto', ''],
            'texto alrededor'        => ['Estoy en -16.5000, -68.1500 frente a la plaza', -16.5, -68.15, 'texto', 'Estoy en frente a la plaza'],
            'maps @'                 => ['https://www.google.com/maps/@-16.5,-68.15,17z', -16.5, -68.15, 'enlace', ''],
            'maps place'             => ['https://www.google.com/maps/place/Casa/@-17.783327,-63.182140,15z/data=x', -17.783327, -63.18214, 'enlace', ''],
            'maps q'                 => ['mira https://maps.google.com/?q=-16.49,-68.12', -16.49, -68.12, 'enlace', 'mira'],
            'maps !3d!4d'            => ['https://www.google.com/maps/place/X/data=!3d-16.4!4d-68.1', -16.4, -68.1, 'enlace', ''],
            'osm'                    => ['https://www.openstreetmap.org/?mlat=-16.5&mlon=-68.1', -16.5, -68.1, 'enlace', ''],
            'geo uri'                => ['geo:-16.5,-68.15', -16.5, -68.15, 'texto', ''],
        ];
    }

    /** @dataProvider locationProvider */
    public function testLocationParsesFormats(string $text, float $lat, float $lng, string $source, string $name): void
    {
        $r = LocationCapture::fromText($text);

        $this->assertNotNull($r, "No detectó: {$text}");
        $this->assertEqualsWithDelta($lat, $r['lat'], 0.000001);
        $this->assertEqualsWithDelta($lng, $r['lng'], 0.000001);
        $this->assertSame($source, $r['source']);
        $this->assertSame($name, $r['name']);
    }

    public function testLocationRejectsLookalikes(): void
    {
        foreach (['', 'hola', 'mi cel 70123456 y 71234567', 'son 10 y 20', 'total 120.50 bs', '0, 0', '0.0, 0.0', '95.5, 10.5', '-16.5, 190.5', 'https://example.com/?q=hola'] as $text) {
            $this->assertNull(LocationCapture::fromText($text), "No debía detectar: {$text}");
        }
    }

    public function testLocationNodeOutputsZoneAndBranches(): void
    {
        $ok = LocationCapture::handle(['source' => '📍 (-16.5, -68.15)', 'center_lat' => -16.49, 'center_lng' => -68.14, 'radius_km' => 5], []);
        $this->assertSame('found', $ok['handle']);
        $this->assertTrue($ok['output']['in_zone']);
        $this->assertSame('-16.5,-68.15', $ok['output']['coords']);
        $this->assertSame('ubicacion', $ok['var']);

        $far = LocationCapture::handle(['source' => '-17.78, -63.18', 'center_lat' => -16.5, 'center_lng' => -68.15, 'radius_km' => 10], []);
        $this->assertFalse($far['output']['in_zone']);
        $this->assertGreaterThan(400, $far['output']['distance_km']);

        $none = LocationCapture::handle(['source' => 'hola'], []);
        $this->assertSame('not_found', $none['handle']);
        $this->assertFalse($none['output']['found']);

        // Sin «source»: usa el mensaje que disparó el flujo.
        $fromTrigger = LocationCapture::handle([], ['trigger' => ['data' => [['body' => "📍 (-16.5, -68.15)\nCasa"]]]]);
        $this->assertSame('found', $fromTrigger['handle']);
        $this->assertSame('Casa', $fromTrigger['output']['name']);

        // Campos explícitos (webhook / herramienta de IA).
        $explicit = LocationCapture::handle(['latitude' => '-16.5', 'longitude' => '-68.15', 'save_as' => 'destino'], []);
        $this->assertSame('campos', $explicit['output']['source']);
        $this->assertSame('destino', $explicit['var']);
    }

    public function testLocationNodeInsideWorkflow(): void
    {
        $wf = $this->make(1, 'delivery', ['graph' => json_encode([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.manual'],
                ['id' => 'loc', 'type' => 'action.location', 'data' => ['source' => '{{ trigger.texto }}', 'center_lat' => -16.5, 'center_lng' => -68.15, 'radius_km' => 10]],
                ['id' => 'ok', 'type' => 'action.respond', 'data' => ['value' => 'Enviamos a {{ vars.ubicacion.coords }} ({{ vars.ubicacion.distance_km }} km)']],
                ['id' => 'ko', 'type' => 'action.respond', 'data' => ['value' => 'Comparte tu ubicación']],
            ],
            'edges' => [
                ['source' => 't', 'target' => 'loc'],
                ['source' => 'loc', 'sourceHandle' => 'found', 'target' => 'ok'],
                ['source' => 'loc', 'sourceHandle' => 'not_found', 'target' => 'ko'],
            ],
        ])]);

        $good = WorkflowRunner::start($wf, ['texto' => "📍 (-16.5, -68.15)\nCasa"], 'manual', sync: true);
        $this->assertSame('ok', $good->status);
        $this->assertSame('Enviamos a -16.5,-68.15 (0 km)', $good->decoded('result'));

        $bad = WorkflowRunner::start($wf, ['texto' => 'hola'], 'manual', sync: true);
        $this->assertSame('Comparte tu ubicación', $bad->decoded('result'));
    }
}
