<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\AgentChat;
use Aero\Workspaces\Classes\AgentRunner;
use Aero\Workspaces\Classes\Hiring;
use Aero\Workspaces\Jobs\RunAgentTurnJob;
use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use PluginTestCase;

require_once __DIR__ . '/FakeLlm.php';

/**
 * La orquestadora trabaja de verdad: reparte pasos a los agentes reales de SU
 * equipo, con un plan aprobado, y responde por el resultado.
 */
class DelegationTest extends PluginTestCase
{
    protected function agent(string $slug, array $tools, bool $orchestrator = false): Staff
    {
        $s = new Staff();
        $s->fill(['name' => ucfirst($slug), 'slug' => $slug, 'role' => 'Rol', 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'automatizacion', 'is_active' => true, 'is_orchestrator' => $orchestrator,
            'system_prompt' => "Eres {$slug}."]);
        $s->save();
        $skill = Skill::create(['kind' => 'official', 'name' => "Skill {$slug}", 'slug' => "skill-{$slug}", 'description' => 'Úsala.', 'body' => "CUERPO-{$slug}", 'tools' => $tools]);
        $s->skills()->attach($skill->id);

        return $s->fresh('skills');
    }

    protected function world(): array
    {
        Staff::query()->update(['is_orchestrator' => false]);
        $boss = $this->agent('jefa', ['workspaces_team', 'team_delegate'], true);
        $ana = $this->agent('ana', ['workspaces_tasks']);
        Hiring::hire(1, 'ana');

        return [$boss, $ana];
    }

    protected function turn(Staff $staff, string $text = 'Hola'): Message
    {
        Message::create(['tenant_id' => 1, 'staff_id' => $staff->id, 'role' => 'user', 'content' => $text, 'status' => 'done']);

        return Message::create(['tenant_id' => 1, 'staff_id' => $staff->id, 'role' => 'assistant', 'status' => 'running']);
    }

    protected function drivers(array $bySlug): void
    {
        AgentRunner::$driverFactory = fn (Staff $s) => $bySlug[$s->slug];
    }

    public function tearDown(): void
    {
        AgentRunner::$driverFactory = null;
        parent::tearDown();
    }

    public function testOnlyTheOrchestratorGetsTheDelegateTool(): void
    {
        [$boss, $ana] = $this->world();
        $rogue = $this->agent('colada', ['workspaces_tasks', 'team_delegate']);

        $this->assertContains('team_delegate', AgentRunner::toolNames($boss));
        $this->assertArrayHasKey('team_delegate', AgentRunner::tools($boss));
        $this->assertNotContains('team_delegate', AgentRunner::toolNames($rogue), 'un agente común no delega aunque su skill lo diga');
        $this->assertArrayNotHasKey('team_delegate', AgentRunner::tools($rogue));
    }

    public function testOrchestratorDelegatesToARealAgentAndReportsItsResult(): void
    {
        [$boss, $ana] = $this->world();
        $workerLlm = new FakeLlm([['text' => 'Hecho: creé el borrador #7.']]);
        $bossLlm = new FakeLlm([
            ['calls' => [['id' => 'd1', 'name' => 'team_delegate', 'arguments' => ['slug' => 'ana', 'brief' => 'Haz el menú con botones', 'agreed_plan' => '1. Un flujo con dos botones que responde al tocar.']]]],
            ['text' => 'Listo: Ana lo dejó en borrador.'],
        ]);
        $this->drivers(['jefa' => $bossLlm, 'ana' => $workerLlm]);

        $reply = $this->turn($boss, 'Quiero un menú');
        AgentRunner::run($reply);
        $reply->refresh();

        $this->assertSame('done', $reply->status);
        $this->assertSame('Listo: Ana lo dejó en borrador.', $reply->content);
        $this->assertSame([], $reply->meta['working'], 'al terminar nadie queda trabajando');

        $thread = Message::thread(1, $ana->id)->orderBy('id')->get();
        $this->assertCount(2, $thread, 'el encargo y la respuesta quedan en el chat de Ana');
        $this->assertStringContainsString('Encargo de Jefa', $thread[0]->content);
        $this->assertStringContainsString('Un flujo con dos botones', $thread[0]->content);
        $this->assertSame('orchestrator', $thread[0]->meta['from']);
        $this->assertSame('done', $thread[1]->status);

        $toolResult = array_values(array_filter($bossLlm->seen[1]['messages'], fn ($m) => ($m['role'] ?? '') === 'tool'));
        $this->assertStringContainsString('Hecho: creé el borrador #7.', $toolResult[0]['content']);
        $this->assertStringContainsString('DELEGADO', strtoupper(json_encode($workerLlm->seen[0]['messages'], JSON_UNESCAPED_UNICODE)), 'el agente sabe que es un encargo delegado');
    }

    public function testDelegationIsRefusedWhenItShouldNotHappen(): void
    {
        [$boss, $ana] = $this->world();
        $sim = $this->agent('simu', []);
        Hiring::hire(1, 'simu');
        $outsider = $this->agent('fuera', ['workspaces_tasks']);

        $call = fn (array $args) => \Aero\Workspaces\Classes\Delegation::run(1, $boss, null, $args + ['brief' => 'Algo concreto', 'agreed_plan' => 'Plan aprobado con varios pasos claros']);

        $this->assertStringContainsString('agreed_plan', $call(['slug' => 'ana', 'agreed_plan' => 'corto'])['error'], 'sin plan acordado no se delega');
        $this->assertStringContainsString('no se puede delegar', $call(['slug' => 'jefa'])['error'], 'ni a sí misma ni a otro orquestador');
        $this->assertStringContainsString('no existe', $call(['slug' => 'nadie'])['error']);
        $this->assertStringContainsString('no está en el equipo', $call(['slug' => 'fuera'])['error'], 'solo agentes contratados por ese cliente');
        $this->assertStringContainsString('no trabaja de verdad', $call(['slug' => 'simu'])['error'], 'un agente simulado no recibe encargos');

        Message::create(['tenant_id' => 1, 'staff_id' => $ana->id, 'role' => 'assistant', 'status' => 'running']);
        $this->assertStringContainsString('ocupado', $call(['slug' => 'ana'])['error']);

        $this->assertSame(0, Message::where('role', 'user')->count(), 'nada de esto crea mensajes');
    }

    public function testDelegationNeverCrossesTenants(): void
    {
        [$boss, $ana] = $this->world();

        $other = \Aero\Workspaces\Classes\Delegation::run(2, $boss, null, ['slug' => 'ana', 'brief' => 'Algo concreto', 'agreed_plan' => 'Plan aprobado con varios pasos claros']);

        $this->assertStringContainsString('no está en el equipo', $other['error'], 'el tenant 2 no contrató a Ana: no se le puede delegar');
        $this->assertSame(0, Message::thread(2, $ana->id)->count());
    }

    public function testWorkerResultsAndFailuresComeBackToTheOrchestrator(): void
    {
        [$boss, $ana] = $this->world();
        $this->drivers([
            // Ana da vueltas con llamadas siempre distintas hasta agotar las 8 permitidas: falla sin terminar.
            'ana'  => new FakeLlm(array_map(fn ($i) => ['calls' => [['id' => "x{$i}", 'name' => 'workspaces_tasks', 'arguments' => ['limit' => $i + 1]]]], range(0, 12))),
            'jefa' => new FakeLlm([
                ['calls' => [['id' => 'd', 'name' => 'team_delegate', 'arguments' => ['slug' => 'ana', 'brief' => 'Algo concreto', 'agreed_plan' => 'Plan aprobado con varios pasos']]]],
                ['text' => 'Ana no pudo; te cuento qué falta.'],
            ]),
        ]);

        $reply = $this->turn($boss);
        AgentRunner::run($reply);

        $this->assertSame('done', $reply->fresh()->status, 'que falle el agente no tumba a la orquestadora');
        $this->assertSame('error', Message::thread(1, $ana->id)->where('role', 'assistant')->first()->status);
    }

    public function testJobClaimsAReplyOnlyOnce(): void
    {
        [$boss] = $this->world();
        $this->drivers(['jefa' => new FakeLlm([['text' => 'ok']])]);
        $reply = Message::create(['tenant_id' => 1, 'staff_id' => $boss->id, 'role' => 'assistant', 'status' => 'pending']);
        Message::create(['tenant_id' => 1, 'staff_id' => $boss->id, 'role' => 'user', 'content' => 'Hola', 'status' => 'done']);

        (new RunAgentTurnJob($reply->id))->handle();
        $this->assertSame('done', $reply->fresh()->status);

        // La cola lo vuelve a ofrecer pasados 90 s: ya no está «pendiente», así que se descarta.
        $again = new FakeLlm([['text' => 'segunda vez']]);
        $this->drivers(['jefa' => $again]);
        (new RunAgentTurnJob($reply->id))->handle();
        $this->assertSame('ok', $reply->fresh()->content);
        $this->assertSame([], $again->seen);
    }

    public function testChatTreatsRunningAsPendingAndExposesWhoIsWorking(): void
    {
        [$boss, $ana] = $this->world();

        Message::create(['tenant_id' => 1, 'staff_id' => $boss->id, 'role' => 'user', 'content' => 'Hola', 'status' => 'done']);
        Message::create(['tenant_id' => 1, 'staff_id' => $boss->id, 'role' => 'assistant', 'status' => 'running', 'meta' => ['working' => ['ana']]]);

        $h = AgentChat::history(1, 'jefa');
        $this->assertTrue($h['pending']);
        $this->assertSame(['ana'], $h['working']);
        $this->assertSame('pending', $h['messages'][1]['status']);

        try {
            AgentChat::send(1, null, 'jefa', 'otra', dispatch: false);
            $this->fail('un turno a la vez');
        }
        catch (\DomainException) {
            $this->assertTrue(true);
        }
    }
}
