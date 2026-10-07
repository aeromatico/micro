<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\AgentChat;
use Aero\Workspaces\Classes\AgentRunner;
use Aero\Workspaces\Classes\Hiring;
use Aero\Workspaces\Classes\Llm\LlmDriver;
use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\Task;
use PluginTestCase;

/** Modelo de mentira: devuelve una vuelta tras otra y guarda lo que recibió. */
class FakeLlm implements LlmDriver
{
    public array $seen = [];
    protected int $i = 0;

    public function __construct(protected array $turns)
    {
    }

    public function chat(array $messages, array $tools): array
    {
        $this->seen[] = ['messages' => $messages, 'tools' => array_keys($tools)];
        $turn = $this->turns[$this->i++] ?? ['text' => 'fin'];

        return ['text' => $turn['text'] ?? null, 'calls' => $turn['calls'] ?? [], 'assistant' => ['role' => 'assistant', 'content' => null]];
    }

    public function toolMessages(array $results): array
    {
        return array_map(fn ($r) => ['role' => 'tool', 'tool_call_id' => $r['id'], 'content' => json_encode($r['result'], JSON_UNESCAPED_UNICODE)], $results);
    }
}

/**
 * El motor de agentes reales: solo usa las herramientas de SUS skills, el tenant
 * lo pone el motor, y la conversación se persiste, se limita y no se cruza.
 */
class AgentRunnerTest extends PluginTestCase
{
    protected function liveAgent(array $tools = ['workspaces_team', 'workspaces_tasks'], string $slug = 'agente'): Staff
    {
        $staff = new Staff();
        $staff->fill(['name' => 'Agente', 'slug' => $slug, 'role' => 'Hace cosas', 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'automatizacion', 'is_active' => true,
            'system_prompt' => 'Eres Agente. [BORRADOR: pendiente de revisión]']);
        $staff->save();

        $skill = Skill::create(['kind' => 'official', 'name' => 'Hacer cosas', 'slug' => 'hacer-' . $slug, 'description' => 'Úsala para hacer cosas.', 'body' => 'INSTRUCCIONES-DEL-SKILL', 'tools' => $tools]);
        Skill::create(['kind' => 'official', 'name' => 'Ajeno', 'slug' => 'ajeno-' . $slug, 'description' => 'No es tuyo.', 'body' => 'SECRETO-AJENO']);
        $staff->skills()->attach($skill->id);

        return $staff->fresh('skills');
    }

    protected function pending(Staff $staff, int $tenant = 1, string $text = 'Hola'): Message
    {
        Hiring::class; // el agente debe estar contratado para chatear
        Message::create(['tenant_id' => $tenant, 'staff_id' => $staff->id, 'role' => 'user', 'content' => $text, 'status' => 'done']);

        return Message::create(['tenant_id' => $tenant, 'staff_id' => $staff->id, 'role' => 'assistant', 'status' => 'pending']);
    }

    public function testToolsComeOnlyFromTheAgentsOwnSkills(): void
    {
        $staff = $this->liveAgent(['workspaces_team', 'workspaces_hire', 'no_existe']);

        $this->assertTrue(AgentRunner::isLive($staff));
        $this->assertEqualsCanonicalizing(['workspaces_team', 'workspaces_hire'], AgentRunner::toolNames($staff), 'solo las que existen y declara un skill suyo');
        $this->assertArrayHasKey('skill_read', AgentRunner::tools($staff));
        $this->assertArrayNotHasKey('workspaces_market', AgentRunner::tools($staff), 'las demás no las tiene');

        $plain = $this->liveAgent([], 'sin-tools');
        $this->assertFalse(AgentRunner::isLive($plain));
    }

    public function testSystemPromptDropsDraftMarkerAndListsSkills(): void
    {
        $prompt = AgentRunner::systemPrompt($this->liveAgent(), 'Mi Negocio');

        $this->assertStringContainsString('Eres Agente.', $prompt);
        $this->assertStringNotContainsString('BORRADOR', $prompt);
        $this->assertStringContainsString('hacer-agente', $prompt);
        $this->assertStringContainsString('Mi Negocio', $prompt);
        $this->assertStringNotContainsString('INSTRUCCIONES-DEL-SKILL', $prompt, 'el cuerpo del skill se lee a pedido, no va en el prompt');
    }

    public function testRunnerLoopsThroughToolsAndSavesTheFinalAnswer(): void
    {
        $staff = $this->liveAgent();
        $reply = $this->pending($staff, 7);
        $llm = new FakeLlm([
            ['calls' => [['id' => 'a', 'name' => 'skill_read', 'arguments' => ['slug' => 'hacer-agente']], ['id' => 'b', 'name' => 'workspaces_team', 'arguments' => ['tenant_id' => 999]]]],
            ['text' => 'Listo, así quedó.'],
        ]);

        AgentRunner::run($reply, $llm);
        $reply->refresh();

        $this->assertSame('done', $reply->status);
        $this->assertSame('Listo, así quedó.', $reply->content);
        $this->assertSame(['skill_read', 'workspaces_team'], array_column($reply->meta['tools'], 'name'));

        $toolMsgs = array_values(array_filter($llm->seen[1]['messages'], fn ($m) => ($m['role'] ?? '') === 'tool'));
        $this->assertStringContainsString('INSTRUCCIONES-DEL-SKILL', $toolMsgs[0]['content']);
        $this->assertStringNotContainsString('999', $toolMsgs[1]['content'], 'el tenant_id inventado por el modelo se ignora');
    }

    public function testAgentCannotUseToolsOrSkillsThatAreNotItsOwn(): void
    {
        $staff = $this->liveAgent();
        $reply = $this->pending($staff);
        $llm = new FakeLlm([
            ['calls' => [
                ['id' => 'a', 'name' => 'workspaces_hire', 'arguments' => ['slug' => 'x', 'confirm' => true]],
                ['id' => 'b', 'name' => 'skill_read', 'arguments' => ['slug' => 'ajeno-agente']],
            ]],
            ['text' => 'ok'],
        ]);

        AgentRunner::run($reply, $llm);

        $results = array_column(array_values(array_filter($llm->seen[1]['messages'], fn ($m) => ($m['role'] ?? '') === 'tool')), 'content');
        $this->assertStringContainsString('no existe o no está entre las tuyas', $results[0]);
        $this->assertStringContainsString('no es tuyo', $results[1]);
        $this->assertStringNotContainsString('SECRETO-AJENO', implode('', $results));
        $this->assertSame(0, \Aero\Workspaces\Models\Hire::count());
    }

    public function testFailuresAreStoredInTheMessageInsteadOfThrowing(): void
    {
        $staff = $this->liveAgent();

        $loop = $this->pending($staff);
        AgentRunner::run($loop, new FakeLlm(array_fill(0, 30, ['calls' => [['id' => 'x', 'name' => 'workspaces_team', 'arguments' => ['n' => random_int(1, 99999)]]]])));
        $this->assertSame('error', $loop->fresh()->status);
        $this->assertStringContainsString('demasiadas vueltas', $loop->fresh()->error);

        $empty = $this->pending($staff);
        AgentRunner::run($empty, new FakeLlm([['text' => '   ']]));
        $this->assertSame('error', $empty->fresh()->status);
    }

    public function testHistoryKeepsWhatWasCreatedAndNeverMixesTenants(): void
    {
        $staff = $this->liveAgent();
        Message::create(['tenant_id' => 1, 'staff_id' => $staff->id, 'role' => 'assistant', 'status' => 'done', 'content' => 'Hecho', 'meta' => ['workflows' => [['id' => 42, 'name' => 'Menú', 'url' => 'x']]]]);
        Message::create(['tenant_id' => 2, 'staff_id' => $staff->id, 'role' => 'user', 'status' => 'done', 'content' => 'SECRETO-DEL-TENANT-2']);
        $reply = $this->pending($staff, 1, 'Sigamos');
        $llm = new FakeLlm([['text' => 'ok']]);

        AgentRunner::run($reply, $llm);

        $dump = json_encode($llm->seen[0]['messages'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Guardaste el borrador #42', $dump);
        $this->assertStringContainsString('Sigamos', $dump);
        $this->assertStringNotContainsString('SECRETO-DEL-TENANT-2', $dump);
    }

    public function testChatRequiresAHiredLiveAgentAndOnePendingTurnAtATime(): void
    {
        $live = $this->liveAgent();
        $dead = $this->liveAgent([], 'simulado');

        try {
            AgentChat::send(1, null, 'agente', 'Hola', dispatch: false);
            $this->fail('sin contratar no se puede chatear');
        }
        catch (\DomainException $e) {
            $this->assertStringContainsString('Contrátalo', $e->getMessage());
        }

        Hiring::hire(1, 'agente');
        Hiring::hire(1, 'simulado');

        try {
            AgentChat::send(1, null, 'simulado', 'Hola', dispatch: false);
            $this->fail('un agente sin herramientas no trabaja de verdad');
        }
        catch (\DomainException $e) {
            $this->assertStringContainsString('simulación', $e->getMessage());
        }

        $data = AgentChat::send(1, null, 'agente', 'Hola', dispatch: false);
        $this->assertTrue($data['pending']);
        $this->assertSame(['user', 'assistant'], array_column($data['messages'], 'role'));

        try {
            AgentChat::send(1, null, 'agente', 'Otra vez', dispatch: false);
            $this->fail('un turno a la vez');
        }
        catch (\DomainException $e) {
            $this->assertStringContainsString('todavía está trabajando', $e->getMessage());
        }

        try {
            AgentChat::history(2, 'agente');
            $this->fail('otro tenant, sin contratar, no ve esta conversación');
        }
        catch (\DomainException) {
            $this->assertTrue(true);
        }

        Hiring::hire(2, 'agente');
        $this->assertSame([], AgentChat::history(2, 'agente')['messages'], 'aun contratándolo, su conversación empieza vacía');
    }

    public function testStalePendingTurnsAreReleased(): void
    {
        $staff = $this->liveAgent();
        Hiring::hire(1, 'agente');
        AgentChat::send(1, null, 'agente', 'Hola', dispatch: false);

        Message::where('status', 'pending')->update(['created_at' => now()->subMinutes(AgentRunner::STALE_MINUTES + 1)]);

        $this->assertFalse(AgentChat::history(1, 'agente')['pending']);
        $this->assertSame('error', Message::where('role', 'assistant')->first()->status);
        $this->assertTrue(AgentChat::send(1, null, 'agente', 'De nuevo', dispatch: false)['pending']);
    }
}
