<?php namespace Aero\Workflows\Tests;

use Aero\Workflows\Classes\AiTools;
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
}
