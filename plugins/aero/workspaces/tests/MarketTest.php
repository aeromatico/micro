<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\Hiring;
use Aero\Workspaces\Classes\Tasks;
use Aero\Workspaces\Classes\Tools;
use Aero\Workspaces\Classes\Workspace;
use Aero\Workspaces\Models\Hire;
use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\Task;
use Aero\Workspaces\Models\TaskRate;
use PluginTestCase;

/**
 * Mercado y Oficina del tenant: el orquestador siempre está, contratar tiene
 * reglas del servidor, los encargos se planifican y aíslan por tenant, y las
 * herramientas del MCP no filtran el prompt ni actúan sin confirmar.
 */
class MarketTest extends PluginTestCase
{
    protected function staff(string $slug, array $extra = [], int $fee = 50): Staff
    {
        $s = new Staff();
        $s->fill($extra + ['name' => ucfirst($slug), 'slug' => $slug, 'role' => 'Rol ' . $slug, 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'contenido', 'is_active' => true,
            'system_prompt' => 'PROMPT-SECRETO-' . $slug]);
        $s->save();
        TaskRate::create(['staff_id' => $s->id, 'task_type' => 'encargo', 'fee' => $fee]);

        return $s;
    }

    protected function world(): array
    {
        $boss = $this->staff('jefa', ['is_orchestrator' => true, 'role' => 'Coordinadora'], 60);
        $ana = $this->staff('ana', ['category' => 'video'], 90);
        $leo = $this->staff('leo', ['category' => 'info'], 30);

        return [$boss, $ana, $leo];
    }

    public function testOnlyOneOrchestratorAtATime(): void
    {
        [$boss, $ana] = $this->world();

        $ana->is_orchestrator = true;
        $ana->save();

        $this->assertFalse((bool) Staff::find($boss->id)->is_orchestrator);
        $this->assertSame($ana->id, Staff::orchestrator()->id);
    }

    public function testOrchestratorIsAlwaysInTheTeamAndNeverSoldInTheMarket(): void
    {
        [$boss, $ana] = $this->world();

        $market = array_column(Workspace::market(1), 'slug');
        $this->assertNotContains('jefa', $market);
        $this->assertContains('ana', $market);

        $this->assertSame(['jefa'], array_column(Workspace::team(1), 'slug'), 'sin contratar a nadie, el equipo es el orquestador');

        Hiring::hire(1, 'ana');
        $this->assertSame(['jefa', 'ana'], array_column(Workspace::team(1), 'slug'));
        $this->assertSame(['jefa'], array_column(Workspace::team(2), 'slug'), 'la contratación es por tenant');
    }

    public function testHiringRulesAreEnforcedByTheServer(): void
    {
        [$boss, $ana, $leo] = $this->world();
        $leo->is_active = false;
        $leo->save();

        $this->assertSame(0, Hiring::hire(1, 'ana')['charged']);
        $this->assertSame(1, Hire::where('tenant_id', 1)->count());

        foreach (['ana' => 'ya está en tu equipo', 'jefa' => 'orquestador', 'leo' => 'no está disponible', 'nadie' => 'no está disponible'] as $slug => $needle) {
            try {
                Hiring::hire(1, $slug);
                $this->fail("debía rechazar {$slug}");
            }
            catch (\DomainException $e) {
                $this->assertStringContainsString($needle, $e->getMessage());
            }
        }

        $this->assertSame(1, Hire::where('tenant_id', 1)->count());
        $this->assertTrue(Hiring::hire(2, 'ana')['agent']['hired'], 'otro tenant sí puede contratar al mismo agente');
        $this->assertSame(2, Hire::where('staff_id', $ana->id)->count());
    }

    public function testTaskPlanCoversTheWholeTeamAndEstimateGrowsWithTheBrief(): void
    {
        $this->world();
        Hiring::hire(1, 'ana');

        $short = Tasks::plan(1, 'Un video');
        $this->assertSame(['jefa', 'ana'], array_column($short['steps'], 'slug'));
        $this->assertTrue($short['steps'][0]['lead']);
        $this->assertSame((int) round(60 * Tasks::factor('Un video')) + (int) round(90 * Tasks::factor('Un video')), $short['estimated_points']);

        $long = Tasks::plan(1, str_repeat('detalle ', 100));
        $this->assertGreaterThan($short['estimated_points'], $long['estimated_points']);

        foreach (['', '   ', str_repeat('x', Tasks::MAX_BRIEF + 1)] as $bad) {
            try {
                Tasks::plan(1, $bad);
                $this->fail('debía rechazar el encargo');
            }
            catch (\DomainException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testTaskNeedsAnOrchestrator(): void
    {
        Staff::query()->update(['is_orchestrator' => false]);
        $this->staff('ana');

        $this->expectException(\DomainException::class);
        Tasks::plan(1, 'Hola');
    }

    public function testSubmitRegistersOneRunningTaskPerTenantAndSettlesByTheClock(): void
    {
        $this->world();
        Hiring::hire(1, 'ana');

        $task = Tasks::submit(1, 'Video de café');
        $this->assertSame('running', $task->status);
        $this->assertSame(0, (int) $task->charged_points, 'el cobro está apagado por defecto');
        $this->assertCount(2, $task->plan['steps']);

        try {
            Tasks::submit(1, 'Otro más');
            $this->fail('un tenant no puede tener dos encargos a la vez');
        }
        catch (\DomainException $e) {
            $this->assertStringContainsString('ya está trabajando', $e->getMessage());
        }

        $this->assertSame('running', Tasks::find(1, $task->id)['status']);

        Task::where('id', $task->id)->update(['finished_at' => now()->subSecond()]);
        $this->assertSame('done', Tasks::find(1, $task->id)['status']);

        $this->assertSame('running', Tasks::submit(1, 'Siguiente')->status, 'terminado el anterior, se puede enviar otro');
        $this->assertSame('running', Tasks::submit(2, 'Otro tenant')->status, 'cada tenant tiene su propio encargo en curso');
    }

    public function testTasksAreIsolatedByTenant(): void
    {
        $this->world();
        $task = Tasks::submit(1, 'Privado del tenant 1');

        $this->assertNull(Tasks::find(2, $task->id));
        $this->assertSame([], Tasks::recent(2));
        $this->assertCount(1, Tasks::recent(1));
    }

    public function testPersonalSkillsBelongToTheirTenant(): void
    {
        $a = Workspace::createSkill(1, 'Tono de marca', 'Úsala cuando haya que escribir como la marca.');
        $b = Workspace::createSkill(1, 'Tono de marca', 'Otra con el mismo nombre.');
        $this->assertNotSame($a['slug'], $b['slug']);

        $mine = array_column(Workspace::skills(1), 'slug');
        $this->assertContains($a['slug'], $mine);
        $this->assertNotContains($a['slug'], array_column(Workspace::skills(2), 'slug'), 'las personales no se ven entre tenants');

        foreach ([['', 'x'], ['Nombre', ''], [str_repeat('n', 41), 'x'], ['Nombre', str_repeat('d', 201)]] as [$name, $desc]) {
            try {
                Workspace::createSkill(1, $name, $desc);
                $this->fail('debía rechazar la skill');
            }
            catch (\DomainException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(2, Skill::where('tenant_id', 1)->count());
    }

    public function testToolsAreRegisteredNeverLeakPromptsAndNeedConfirmation(): void
    {
        $this->world();

        $registered = \Aero\Chatbots\Classes\AiToolRegistry::all(1);
        foreach (['workspaces_market', 'workspaces_agent', 'workspaces_team', 'workspaces_hire', 'workspaces_skills', 'workspaces_submit_task', 'workspaces_tasks'] as $name) {
            $this->assertSame('workspaces', $registered[$name]['category'] ?? null, $name);
        }

        $dump = json_encode([Tools::market([], 1), Tools::agent(['slug' => 'ana'], 1), Tools::team([], 1)], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('PROMPT-SECRETO', $dump);
        $this->assertStringNotContainsString('system_prompt', $dump);

        $preview = Tools::hire(['slug' => 'ana'], 1);
        $this->assertFalse($preview['confirmed']);
        $this->assertSame(0, Hire::count(), 'sin confirm no se contrata');

        $this->assertTrue(Tools::hire(['slug' => 'ana', 'confirm' => true], 1)['confirmed']);
        $this->assertSame(1, Hire::where('tenant_id', 1)->count());

        $plan = Tools::submitTask(['brief' => 'Un reel'], 1);
        $this->assertFalse($plan['confirmed']);
        $this->assertTrue($plan['simulated']);
        $this->assertSame(0, Task::count(), 'sin confirm no se crea el encargo');

        $done = Tools::submitTask(['brief' => 'Un reel', 'confirm' => true], 1);
        $this->assertTrue($done['confirmed']);
        $this->assertSame(1, Task::where('tenant_id', 1)->count());
        $this->assertArrayHasKey('error', Tools::hire(['slug' => 'jefa', 'confirm' => true], 1), 'el orquestador no se contrata');
    }
}
