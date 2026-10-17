<?php namespace Aero\Help\Tests;

use Aero\Help\Classes\Engine;
use Aero\Help\Classes\ScreenContext;
use Aero\Help\Classes\Search;
use Aero\Help\Models\Flow;
use Aero\Help\Models\Session;
use Aero\Help\Models\Topic;
use PluginTestCase;

/**
 * Humo y lógica del motor: tablas, permisos, interpretación de la URL, flujos
 * (incluida la validación de saltos), manipulación de opciones y que una
 * conversación solo la ve quien la abrió.
 */
class HelpTest extends PluginTestCase
{
    protected function makeFlow(array $over = []): Flow
    {
        return Flow::create($over + [
            'slug'  => 'demo',
            'title' => 'Demo',
            'steps' => [
                ['key' => 'banco', 'type' => 'choice', 'save_as' => 'banco', 'body' => '¿Cuál?', 'options' => [
                    ['label' => 'BNB', 'value' => 'bnb', 'next' => 'fin_bnb'],
                    ['label' => 'Otro', 'value' => 'otro', 'next' => 'fin'],
                ]],
                ['key' => 'fin_bnb', 'type' => 'message', 'numbered' => true, 'title' => 'Datos', 'body' => 'Elegiste {{banco}}.', 'next' => 'fin'],
                ['key' => 'fin', 'type' => 'done', 'body' => '¿Resuelto?'],
            ],
        ]);
    }

    protected function makeSession(int $userId = 1, ?Flow $flow = null): Session
    {
        return Session::create([
            'user_id' => $userId, 'url' => 'http://x/backend/aero/pay/bankaccounts/create', 'status' => 'open',
            'flow_id' => $flow?->id, 'state' => ['waiting' => null, 'answers' => []], 'transcript' => [],
        ]);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_help_topics', 'aero_help_flows', 'aero_help_sessions', 'aero_help_searches'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Help\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        $this->assertContains('aero.help.use', $declared);
        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared, "El menú exige un permiso no declarado: {$p}");
            }
            foreach (($item['sideMenu'] ?? []) as $side) {
                foreach (($side['permissions'] ?? []) as $p) {
                    $this->assertContains($p, $declared);
                }
            }
        }
    }

    public function testScreenContextParsesBackendUrls(): void
    {
        $ctx = ScreenContext::parse('https://x.test/backend/aero/pay/bankaccounts/update/5');
        $this->assertSame('pay/bankaccounts/update', $ctx['key']);

        $this->assertSame('pay/qrcodes/index', ScreenContext::parse('/backend/aero/pay/qrcodes')['key']);
        $this->assertSame('', ScreenContext::parse('/backend')['key']);
    }

    public function testSearchIgnoresFillerWords(): void
    {
        $tokens = Search::tokens('¿Cómo puedo conectar una cuenta bancaria?');

        $this->assertContains('conectar', $tokens);
        $this->assertContains('cuenta', $tokens);
        $this->assertNotContains('como', $tokens);
        $this->assertNotContains('una', $tokens);
    }

    public function testFlowRejectsJumpsToMissingSteps(): void
    {
        $this->expectException(\ApplicationException::class);

        $this->makeFlow(['steps' => [['key' => 'a', 'type' => 'message', 'body' => 'x', 'next' => 'no_existe']]]);
    }

    public function testFlowRejectsDuplicateKeys(): void
    {
        $this->expectException(\ApplicationException::class);

        $this->makeFlow(['steps' => [['key' => 'a', 'type' => 'message'], ['key' => 'a', 'type' => 'message']]]);
    }

    public function testFlowWalksChoiceAnswersAndInterpolates(): void
    {
        $flow = $this->makeFlow();
        $topic = Topic::create(['slug' => 't', 'title' => 'Tema', 'flow_id' => $flow->id, 'is_published' => true]);
        $engine = new Engine($this->makeSession(1));

        $first = $engine->respond('choose', 'card:topic:' . $topic->id);
        $this->assertContains('buttons', array_column($first['blocks'], 'type'));

        $second = $engine->respond('choose', 'opt:bnb');
        $types = array_column($second['blocks'], 'type');
        $this->assertContains('step', $types);
        $texts = implode(' ', array_column(array_filter($second['blocks'], fn ($b) => $b['type'] === 'text'), 'text'));
        $this->assertStringContainsString('Elegiste BNB.', $texts);
    }

    public function testClientCannotInventOptionsOrJumpSteps(): void
    {
        $flow = $this->makeFlow();
        $topic = Topic::create(['slug' => 't', 'title' => 'Tema', 'flow_id' => $flow->id, 'is_published' => true]);
        $engine = new Engine($this->makeSession(1));
        $engine->respond('choose', 'card:topic:' . $topic->id);

        $res = $engine->respond('choose', 'opt:fin_bnb');   // un paso, no una opción

        $this->assertSame('banco', ((array) $engine->session()->state)['waiting']);
        $texts = implode(' ', array_column(array_filter($res['blocks'], fn ($b) => $b['type'] === 'text'), 'text'));
        $this->assertStringContainsString('No reconozco', $texts);
    }

    public function testEachTurnReturnsOnlyItsOwnBlocks(): void
    {
        $engine = new Engine($this->makeSession(1));

        $engine->respond('text', 'algo rarísimo inexistente');
        $second = $engine->respond('choose', 'menu');

        $this->assertStringNotContainsString('No encontré', json_encode($second['blocks'], JSON_UNESCAPED_UNICODE));
    }

    public function testCardStepsStaySilentWhenTheCardDoesNotExist(): void
    {
        $flow = $this->makeFlow(['steps' => [
            ['key' => 'v', 'type' => 'video', 'ref' => 'pay', 'body' => 'Mira este video:', 'next' => 'fin'],
            ['key' => 'fin', 'type' => 'done', 'body' => '¿Resuelto?'],
        ]]);
        $topic = Topic::create(['slug' => 't', 'title' => 'Tema', 'flow_id' => $flow->id, 'is_published' => true]);

        $res = (new Engine($this->makeSession(1)))->respond('choose', 'card:topic:' . $topic->id);

        $this->assertStringNotContainsString('Mira este video', json_encode($res['blocks']));
        $this->assertStringContainsString('Resuelto', json_encode($res['blocks']));
    }

    public function testResolvedMarksTheSession(): void
    {
        $engine = new Engine($session = $this->makeSession(1));

        $engine->respond('choose', 'resolved');

        $this->assertSame('resolved', $session->fresh()->status);
        $this->assertNotNull($session->fresh()->resolved_at);
    }

    public function testSearchWithoutResultsIsLogged(): void
    {
        $engine = new Engine($this->makeSession(1));

        $res = $engine->respond('text', 'algo rarísimo inexistente');

        $this->assertContains('buttons', array_column($res['blocks'], 'type'));
        $this->assertSame(1, \Aero\Help\Models\SearchLog::withoutResults()->count());
    }

    public function testOnlyTheOwnerCanResumeASession(): void
    {
        $owner = $this->backendUser('owner');
        $other = $this->backendUser('other');
        $session = $this->makeSession($owner->id);

        \BackendAuth::login($other);
        $this->assertNull(Engine::find($session->id));

        \BackendAuth::login($owner);
        $this->assertNotNull(Engine::find($session->id));
    }

    public function testQuickReportNeedsAvailableChannelAndEnoughText(): void
    {
        $this->expectException(\ApplicationException::class);

        \Aero\Help\Classes\Escalation::quick('inexistente', 'algo no funciona en esta pantalla');
    }

    public function testChannelsComeWithTheFirstTurn(): void
    {
        $res = (new Engine($this->makeSession(1)))->respond('choose', 'menu');

        $this->assertArrayHasKey('channels', $res);
    }

    protected function backendUser(string $login): \Backend\Models\User
    {
        return \Backend\Models\User::create([
            'login' => $login, 'email' => "{$login}@example.test", 'first_name' => ucfirst($login), 'last_name' => 'Test',
            'password' => 'Secreta-1234', 'password_confirmation' => 'Secreta-1234',
        ]);
    }
}
