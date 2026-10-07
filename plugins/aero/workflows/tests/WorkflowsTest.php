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

    public function testReplyWithoutInboundMessageIsTestModeNotAnError(): void
    {
        $out = \Aero\Workflows\Classes\BuiltinNodes::reply(['body' => 'Hola'], ['trigger' => ['texto' => 'x']], 1);

        $this->assertFalse($out['output']['sent']);
        $this->assertSame('Hola', $out['respond']);

        $this->expectException(\InvalidArgumentException::class);
        \Aero\Workflows\Classes\BuiltinNodes::reply(['body' => ''], [], 1);
    }

    public function testGraphLayoutPutsEachNodeBelowItsParentsWithoutOverlaps(): void
    {
        $graph = ['nodes' => [
            ['id' => 't', 'type' => 'trigger.manual'], ['id' => 'c', 'type' => 'logic.condition'],
            ['id' => 'a', 'type' => 'action.respond'], ['id' => 'b', 'type' => 'action.respond'], ['id' => 'fin', 'type' => 'action.respond'],
        ], 'edges' => [
            ['source' => 't', 'target' => 'c'], ['source' => 'c', 'sourceHandle' => 'true', 'target' => 'a'],
            ['source' => 'c', 'sourceHandle' => 'false', 'target' => 'b'], ['source' => 'a', 'target' => 'fin'], ['source' => 'b', 'target' => 'fin'],
            ['source' => 'fin', 'target' => 'c'], // ciclo: no debe colgar ni romper
        ]];

        $out = \Aero\Workflows\Classes\GraphLayout::apply($graph);
        $pos = collect($out['nodes'])->mapWithKeys(fn ($n) => [$n['id'] => $n['position']])->all();

        $this->assertLessThan($pos['c']['y'], $pos['t']['y']);
        $this->assertLessThan($pos['a']['y'], $pos['c']['y']);
        $this->assertSame($pos['a']['y'], $pos['b']['y']);
        $this->assertNotSame($pos['a']['x'], $pos['b']['x']);
        $this->assertCount(5, array_unique(array_map(fn ($p) => $p['x'] . ',' . $p['y'], $pos)), 'Ningún nodo queda encima de otro.');
        $this->assertSame($graph['edges'], $out['edges'], 'Las conexiones no se tocan.');
    }

    // --- Nodos interactivos de Hello (botones, menú, enlace, ubicación, llamada) ---

    protected function useInteractiveNodes(): void
    {
        if (!class_exists(\Aero\Hello\Classes\Workflows\InteractiveNodes::class)) {
            $this->markTestSkipped('Aero.Hello no está instalado.');
        }

        \Event::listen('aero.workflows.registerNodes', fn () => \Aero\Hello\Classes\Workflows\InteractiveNodes::definitions());
        \Aero\Workflows\Classes\NodeRegistry::flush();
    }

    protected function interactiveGraph(string $type, array $data): array
    {
        return [
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.message', 'data' => []],
                ['id' => 'x', 'type' => $type, 'data' => $data],
            ],
            'edges' => [['source' => 't', 'target' => 'x']],
        ];
    }

    public function testInteractiveNodesEnforceWhatsappLimits(): void
    {
        $this->useInteractiveNodes();
        $v = fn (string $type, array $data) => \Aero\Workflows\Classes\GraphValidator::validate($this->interactiveGraph($type, $data));

        $this->assertSame([], $v('hello.reply_buttons', ['body' => 'Hola {{ vars.nombre }}', 'buttons' => "Precios\nAsesor | asesor"]));
        $this->assertNotEmpty($v('hello.reply_buttons', ['body' => 'x', 'buttons' => "a\nb\nc\nd"]), 'máx. 3 botones');
        $this->assertNotEmpty($v('hello.reply_buttons', ['body' => 'x', 'buttons' => str_repeat('a', 21)]), 'botón > 20');
        $this->assertNotEmpty($v('hello.reply_buttons', ['body' => '', 'buttons' => 'a']), 'texto obligatorio');
        $this->assertNotEmpty($v('hello.reply_buttons', ['body' => str_repeat('a', 1025), 'buttons' => 'a']), 'texto > 1024');
        $this->assertNotEmpty($v('hello.reply_buttons', ['body' => 'x', 'buttons' => "a | mismo\nb | mismo"]), 'ids repetidos');
        $this->assertNotEmpty($v('hello.reply_buttons', ['body' => 'x', 'buttons' => 'a | id con espacio']), 'id inválido');

        $rows = implode("\n", array_map(fn ($i) => "Opción {$i}", range(1, 11)));
        $this->assertNotEmpty($v('hello.reply_list', ['body' => 'x', 'list_rows' => $rows]), 'máx. 10 filas');
        $this->assertSame([], $v('hello.reply_list', ['body' => 'x', 'list_rows' => "Pizza | Desde Bs 40 | pizza\nCafé"]));
        $this->assertNotEmpty($v('hello.reply_list', ['body' => 'x', 'list_rows' => 'Pizza | ' . str_repeat('d', 73)]), 'descripción > 72');

        $this->assertNotEmpty($v('hello.reply_link', ['body' => 'x', 'cta_text' => 'Ver', 'cta_url' => 'javascript:alert(1)']));
        $this->assertNotEmpty($v('hello.reply_link', ['body' => 'x', 'cta_text' => '', 'cta_url' => 'https://a.com']));
        $this->assertSame([], $v('hello.reply_link', ['body' => 'x', 'cta_text' => 'Ver', 'cta_url' => 'https://a.com/{{ vars.id }}']));
        $this->assertSame([], $v('hello.reply_location_request', ['body' => 'Comparte tu ubicación']));
        $this->assertSame([], $v('hello.reply_call', ['body' => 'Llámanos']));
    }

    public function testInteractiveNodesAreSideEffectsAndNeverInAutomaticDrafts(): void
    {
        $this->useInteractiveNodes();

        $errors = \Aero\Workflows\Classes\GraphValidator::validate(
            $this->interactiveGraph('hello.reply_location_request', ['body' => 'x']),
            draft: true
        );

        $this->assertNotEmpty($errors);
    }

    public function testInteractiveNodeWithoutInboundMessageOnlyPreviews(): void
    {
        $this->useInteractiveNodes();

        $out = \Aero\Hello\Classes\Workflows\InteractiveNodes::buttons(['body' => 'Elige', 'buttons' => "Sí\nNo"], ['trigger' => []], 1);

        $this->assertFalse($out['output']['sent']);
        $this->assertStringContainsString('Elige', $out['respond']);

        $this->expectException(\InvalidArgumentException::class);
        \Aero\Hello\Classes\Workflows\InteractiveNodes::buttons(['body' => 'x', 'buttons' => "a\nb\nc\nd"], ['trigger' => []], 1);
    }

    public function testInteractiveNodeRejectsForeignContactFailClosed(): void
    {
        $this->useInteractiveNodes();

        $this->expectException(\RuntimeException::class);
        \Aero\Hello\Classes\Workflows\InteractiveNodes::buttons(
            ['body' => 'x', 'buttons' => 'a'],
            ['trigger' => ['data' => [['contact_id' => 999, 'account_id' => 999]]]],
            1
        );
    }

    public function testPublishedWorkflowCannotSaveBrokenInteractiveNodeButDraftCan(): void
    {
        $this->useInteractiveNodes();
        $graph = json_encode($this->interactiveGraph('hello.reply_buttons', ['body' => 'x', 'buttons' => "a\nb\nc\nd"]));

        $draft = Workflow::create([
            'tenant_id' => 1, 'name' => 'b1', 'slug' => 'b1', 'is_active' => false, 'status' => 'draft',
            'trigger_type' => 'manual', 'graph' => $graph,
        ]);
        $this->assertTrue($draft->exists);

        $this->expectException(\ApplicationException::class);
        Workflow::create([
            'tenant_id' => 1, 'name' => 'b2', 'slug' => 'b2', 'is_active' => true, 'status' => 'published',
            'trigger_type' => 'manual', 'graph' => $graph,
        ]);
    }

    public function testMessageTriggerCanFilterByTappedOptionId(): void
    {
        $pass = new \ReflectionMethod(Triggers::class, 'messagePasses');
        $pass->setAccessible(true);

        $tap = fn (?string $id) => (object) [
            'direction' => 'inbound', 'account_id' => 1, 'body' => 'Ver precios',
            'provider_payload' => $id ? ['interactive_type' => 'button_reply', 'interactive_id' => $id] : null,
        ];

        $config = ['interactive_id' => ['ver_precios_1', 'asesor']];

        $this->assertTrue($pass->invoke(null, $config, $tap('asesor')));
        $this->assertFalse($pass->invoke(null, $config, $tap('otro')));
        $this->assertFalse($pass->invoke(null, $config, $tap(null)), 'un mensaje de texto no es un toque');
        $this->assertTrue($pass->invoke(null, ['interactive_id' => 'asesor'], $tap('asesor')), 'acepta un solo id');
        $this->assertTrue($pass->invoke(null, ['keyword' => 'precios'], $tap(null)), 'sin interactive_id no cambia nada');
    }

    public function testDeletingWorkflowRemovesItsRunsAndStepsOnly(): void
    {
        $a = $this->make(1, 'borrar-a');
        $b = $this->make(1, 'conservar-b');

        $runA = WorkflowRunner::start($a, ['plan' => 'pro', 'name' => 'Ana'], 'manual', sync: true);
        $runB = WorkflowRunner::start($b, ['plan' => 'pro', 'name' => 'Beto'], 'manual', sync: true);

        $this->assertGreaterThan(0, \Aero\Workflows\Models\RunStep::where('run_id', $runA->id)->count());

        $a->delete();

        $this->assertNull(Workflow::find($a->id));
        $this->assertSame(0, \Aero\Workflows\Models\Run::where('workflow_id', $a->id)->count());
        $this->assertSame(0, \Aero\Workflows\Models\RunStep::where('run_id', $runA->id)->count());
        $this->assertNotNull(\Aero\Workflows\Models\Run::find($runB->id), 'las ejecuciones de otros workflows no se tocan');
        $this->assertGreaterThan(0, \Aero\Workflows\Models\RunStep::where('run_id', $runB->id)->count());
    }

    // --- Exportar / importar ---

    protected function porterWorkflow(array $extra = []): Workflow
    {
        $graph = [
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.message', 'data' => []],
                ['id' => 'm', 'type' => 'action.message', 'data' => ['to' => '{{ trigger.phone }}', 'body' => 'Hola', 'account_id' => 7]],
                ['id' => 'h', 'type' => 'action.http', 'data' => [
                    'connector_id' => 3, 'url' => 'https://example.com', 'method' => 'POST',
                    'payload' => ['Authorization' => 'Bearer abc123', 'max_tokens' => 50, 'tenant' => 'x'],
                ]],
                ['id' => 'd', 'type' => 'action.message', 'data' => ['to' => '1', 'body' => 'x', 'account_id' => '{{ vars.cuenta }}']],
            ],
            'edges' => [['source' => 't', 'target' => 'm'], ['source' => 'm', 'target' => 'h'], ['source' => 'h', 'target' => 'd']],
        ];

        return Workflow::create($extra + [
            'tenant_id' => 1, 'name' => 'Ventas', 'slug' => 'ventas', 'description' => 'Responde ventas',
            'is_active' => true, 'status' => 'published', 'trigger_type' => 'message',
            'trigger_config' => json_encode(['keyword' => 'hola', 'account_id' => 7]), 'graph' => json_encode($graph),
            'expose_as_tool' => true, 'tool_description' => 'Úsalo para ventas',
        ]);
    }

    public function testExportStripsTenantIdsAndSecrets(): void
    {
        $export = \Aero\Workflows\Classes\WorkflowPorter::export($this->porterWorkflow());
        $json = json_encode($export);

        $this->assertSame('aero.workflow', $export['format']);
        $this->assertStringNotContainsString('abc123', $json, 'el token no viaja');
        $this->assertArrayNotHasKey('account_id', $export['workflow']['trigger_config']);
        $this->assertArrayNotHasKey('tenant_id', $export['workflow']);
        $this->assertArrayNotHasKey('is_active', $export['workflow']);

        $nodes = array_column($export['workflow']['graph']['nodes'], null, 'id');
        $this->assertArrayNotHasKey('account_id', $nodes['m']['data']);
        $this->assertArrayNotHasKey('connector_id', $nodes['h']['data']);
        $this->assertSame(50, $nodes['h']['data']['payload']['max_tokens'], 'max_tokens no es una credencial');
        $this->assertSame('{{ vars.cuenta }}', $nodes['d']['data']['account_id'], 'una plantilla no es un id fijo');
        $this->assertGreaterThanOrEqual(4, count($export['reconnect']));
    }

    public function testImportCreatesInactiveDraftInTheImportersTenantOnly(): void
    {
        $porter = \Aero\Workflows\Classes\WorkflowPorter::class;
        $export = $porter::export($this->porterWorkflow());
        $export['workflow']['tenant_id'] = 99;
        $export['workflow']['is_active'] = true;
        $export['workflow']['status'] = 'published';
        $export['workflow']['expose_as_tool'] = true;

        $result = $porter::import($porter::parse(json_encode($export))[0], 2);
        $w = $result['workflow'];

        $this->assertSame(2, (int) $w->tenant_id, 'el tenant lo pone quien importa, nunca el archivo');
        $this->assertFalse((bool) $w->is_active);
        $this->assertSame('draft', $w->status);
        $this->assertFalse((bool) $w->expose_as_tool);
        $this->assertStringContainsString('Pendiente tras importar', (string) $w->description);
        $this->assertNotEmpty($result['reconnect']);

        $again = $porter::import($porter::parse(json_encode($export))[0], 2)['workflow'];
        $this->assertNotSame($w->slug, $again->slug, 'el código no se repite en el tenant');
    }

    public function testImportRejectsUnknownNodesBadFilesAndNewerFormats(): void
    {
        $porter = \Aero\Workflows\Classes\WorkflowPorter::class;
        $export = $porter::export($this->porterWorkflow());

        $unknown = $export;
        $unknown['workflow']['graph']['nodes'][] = ['id' => 'z', 'type' => 'plugin.inexistente', 'data' => []];

        try {
            $porter::import($unknown, 1);
            $this->fail('debía rechazar un nodo desconocido');
        }
        catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('plugin.inexistente', $e->getMessage());
        }

        foreach (['', 'no es json', '{"format":"otra.cosa","version":1}', json_encode(['format' => 'aero.workflow', 'version' => 99])] as $bad) {
            try {
                $porter::parse($bad);
                $this->fail('debía rechazar: ' . $bad);
            }
            catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $porter::parse(str_repeat('a', $porter::MAX_BYTES + 1));
    }

    public function testBundleRoundTripAndLimit(): void
    {
        $porter = \Aero\Workflows\Classes\WorkflowPorter::class;
        $one = $porter::export($this->porterWorkflow());
        $two = $porter::export($this->porterWorkflow(['slug' => 'ventas-2', 'name' => 'Ventas 2']));

        $this->assertCount(2, $porter::parse(json_encode($porter::bundle([$one, $two]))));

        $this->expectException(\InvalidArgumentException::class);
        $porter::parse(json_encode($porter::bundle(array_fill(0, $porter::MAX_WORKFLOWS + 1, $one))));
    }

    // --- Herramientas del constructor (agente que diseña workflows) ---

    protected function builderGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger.manual', 'data' => []],
                ['id' => 'n2', 'type' => 'logic.condition', 'data' => ['left' => '{{ trigger.monto }}', 'op' => 'gt', 'right' => '100']],
                ['id' => 'n3', 'type' => 'action.respond', 'data' => ['value' => 'Grande']],
                ['id' => 'n4', 'type' => 'action.respond', 'data' => ['value' => 'Pequeño']],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
                ['source' => 'n2', 'target' => 'n3', 'sourceHandle' => 'true'],
                ['source' => 'n2', 'target' => 'n4', 'sourceHandle' => 'false'],
            ],
        ];
    }

    protected function draftArgs(array $extra = []): array
    {
        return $extra + [
            'name' => 'Clasificar monto', 'description' => 'Clasifica por monto',
            'agreed_plan' => '1. Recibe el monto. 2. Si pasa de 100 responde Grande, si no Pequeño.',
            'trigger_type' => 'manual', 'graph' => $this->builderGraph(),
        ];
    }

    public function testBuilderToolsAreRegisteredInTheirOwnCategoryAndStayOptIn(): void
    {
        $tools = \Aero\Chatbots\Classes\AiToolRegistry::all(1);

        foreach (['workflows_context', 'workflows_catalog', 'workflows_list', 'workflows_get', 'workflows_validate', 'workflows_save_draft'] as $name) {
            $this->assertSame('workflows_builder', $tools[$name]['category'] ?? null, $name);
        }

        $this->assertArrayHasKey('workflows_builder', \Aero\Chatbots\Classes\AiToolRegistry::categoryLabels());
    }

    public function testBuilderContextAndCatalog(): void
    {
        $B = \Aero\Workflows\Classes\BuilderTools::class;

        $ctx = $B::context([], 1);
        $this->assertSame(60, $ctx['limits']['max_nodes']);
        $this->assertArrayHasKey('message', $ctx['triggers']);

        $short = $B::catalog([], 1);
        $this->assertContains('logic.condition', array_column($short['nodes'], 'type'));

        $detail = $B::catalog(['types' => ['logic.condition', 'no.existe']], 1);
        $this->assertSame(['true', 'false'], array_column($detail['nodes']['logic.condition']['outputs'], 'id'));
        $this->assertSame(['no.existe'], $detail['unknown_types']);
        $this->assertArrayNotHasKey('handler', $detail['nodes']['logic.condition']);
    }

    public function testBuilderValidateUsesEditorRules(): void
    {
        $B = \Aero\Workflows\Classes\BuilderTools::class;

        $ok = $B::validate(['graph' => $this->builderGraph(), 'trigger_type' => 'manual'], 1);
        $this->assertTrue($ok['valid'], json_encode($ok));

        $mismatch = $B::validate(['graph' => $this->builderGraph(), 'trigger_type' => 'message'], 1);
        $this->assertFalse($mismatch['valid']);

        $loose = $this->builderGraph();
        array_pop($loose['edges']);
        $this->assertFalse($B::validate(['graph' => $loose, 'trigger_type' => 'manual'], 1)['valid'], 'salida sin conectar');

        $this->assertFalse($B::validate(['graph' => $this->builderGraph(), 'trigger_type' => 'event'], 1)['valid'], 'event sin nombre de evento');
    }

    public function testBuilderSaveDraftRequiresAgreementAndAlwaysSavesInactiveDraftForTheEngineTenant(): void
    {
        $B = \Aero\Workflows\Classes\BuilderTools::class;

        $noPlan = $B::saveDraft($this->draftArgs(['agreed_plan' => '']), 7);
        $this->assertFalse($noPlan['saved']);

        $saved = $B::saveDraft($this->draftArgs(['tenant_id' => 99, 'is_active' => true, 'status' => 'published', 'expose_as_tool' => true]), 7);
        $this->assertTrue($saved['saved'], json_encode($saved));

        $w = Workflow::find($saved['id']);
        $this->assertSame(7, (int) $w->tenant_id, 'el tenant lo pone el motor, no los argumentos');
        $this->assertSame('draft', $w->status);
        $this->assertFalse((bool) $w->is_active);
        $this->assertFalse((bool) $w->expose_as_tool);
        $this->assertStringContainsString('Plan acordado', (string) $w->description);
        $this->assertNotNull($w->jsonField('graph')['nodes'][0]['position'] ?? null, 'se acomoda por capas');
    }

    public function testBuilderSaveDraftRejectsInvalidGraphsAndNeverTouchesPublishedOrForeignWorkflows(): void
    {
        $B = \Aero\Workflows\Classes\BuilderTools::class;

        $bad = $this->builderGraph();
        array_pop($bad['edges']);
        $r = $B::saveDraft($this->draftArgs(['graph' => $bad]), 7);
        $this->assertFalse($r['saved']);
        $this->assertNotEmpty($r['errors']);
        $this->assertSame(0, Workflow::where('tenant_id', 7)->count(), 'un grafo inválido no se guarda');

        $published = $this->make(7, 'publicado', ['status' => 'published']);
        $foreign = $this->make(8, 'ajeno', ['status' => 'draft']);

        $r = $B::saveDraft($this->draftArgs(['workflow_id' => $published->id]), 7);
        $this->assertFalse($r['saved']);
        $this->assertStringContainsString('borradores', $r['errors'][0]);

        $r = $B::saveDraft($this->draftArgs(['workflow_id' => $foreign->id]), 7);
        $this->assertFalse($r['saved'], 'no se puede reemplazar el de otro tenant');
        $this->assertSame('ajeno', Workflow::find($foreign->id)->name);

        $mine = $this->make(7, 'mio', ['status' => 'draft']);
        $r = $B::saveDraft($this->draftArgs(['workflow_id' => $mine->id, 'name' => 'Renombrado']), 7);
        $this->assertTrue($r['saved']);
        $this->assertSame('Renombrado', Workflow::find($mine->id)->name);

        $this->assertSame(['error' => 'No existe ese workflow en este cliente.'], $B::get(['id' => $foreign->id], 7));
        $this->assertSame([], array_filter($B::listing([], 7)['workflows'], fn ($w) => $w['id'] === $foreign->id));
    }

    public function testBuilderDropsWebhookSecretsAndUnknownTriggerConfig(): void
    {
        $B = \Aero\Workflows\Classes\BuilderTools::class;
        $graph = ['nodes' => [['id' => 't', 'type' => 'trigger.webhook', 'data' => []], ['id' => 'r', 'type' => 'action.respond', 'data' => ['value' => 'ok']]], 'edges' => [['source' => 't', 'target' => 'r']]];

        $r = $B::saveDraft($this->draftArgs(['trigger_type' => 'webhook', 'graph' => $graph, 'trigger_config' => ['secret' => 'abc', 'otro' => 1]]), 7);
        $this->assertTrue($r['saved'], json_encode($r));
        $this->assertSame([], Workflow::find($r['id'])->jsonField('trigger_config'));
    }

    /** Los patrones del skill del agente diseñador deben seguir siendo válidos con el editor real. */
    public function testEveryPatternInTheDesignerSkillValidates(): void
    {
        $this->useInteractiveNodes();

        if (class_exists(\Aero\Shop\Classes\Workflows\CatalogNodes::class)) {
            \Event::listen('aero.workflows.registerNodes', fn () => \Aero\Shop\Classes\Workflows\CatalogNodes::definitions());
            \Aero\Workflows\Classes\NodeRegistry::flush();
        }

        $md = file_get_contents(__DIR__ . '/../skills/workflow-designer/references/patterns.md');
        preg_match_all('/```json\n(.*?)\n```/s', $md, $blocks);
        $this->assertGreaterThanOrEqual(8, count($blocks[1]));

        foreach ($blocks[1] as $i => $json) {
            $block = json_decode($json, true);
            $this->assertIsArray($block, "bloque #{$i} no es JSON válido");

            $graph = $block['graph'] ?? $block;
            $trigger = $block['trigger_type'] ?? null;

            foreach ($graph['nodes'] as $node) {
                if (str_starts_with($node['type'], 'trigger.')) {
                    $trigger ??= substr($node['type'], 8);
                }
            }

            $result = \Aero\Workflows\Classes\BuilderTools::validate(
                ['graph' => $graph, 'trigger_type' => $trigger, 'trigger_config' => $block['trigger_config'] ?? []],
                1
            );

            $this->assertTrue($result['valid'], "patrón #{$i} (" . ($block['name'] ?? 'sin nombre') . ') inválido: ' . json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
        }
    }

    public function testParallelBranchesThatJoinRunTheJoinNodeTwice(): void
    {
        // Lo que el skill le advierte al agente: la unión tras ramas paralelas se ejecuta una vez por rama.
        $wf = $this->make(1, 'paralelo', ['graph' => json_encode([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.manual', 'data' => []],
                ['id' => 'a', 'type' => 'logic.set', 'data' => ['name' => 'a', 'value' => '1']],
                ['id' => 'b', 'type' => 'logic.set', 'data' => ['name' => 'b', 'value' => '1']],
                ['id' => 'j', 'type' => 'action.respond', 'data' => ['value' => 'fin']],
            ],
            'edges' => [
                ['source' => 't', 'target' => 'a'], ['source' => 't', 'target' => 'b'],
                ['source' => 'a', 'target' => 'j'], ['source' => 'b', 'target' => 'j'],
            ],
        ])]);

        $run = WorkflowRunner::start($wf, [], 'manual', sync: true);

        $this->assertSame(2, \Aero\Workflows\Models\RunStep::where('run_id', $run->id)->where('node_id', 'j')->count());
    }
}
