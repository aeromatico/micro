<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\AgentRunner;
use Aero\Workspaces\Classes\Llm\FallbackLlm;
use Aero\Workspaces\Classes\Llm\LlmDriver;
use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;
use PluginTestCase;

require_once __DIR__ . '/FakeLlm.php';

/** Un modelo que nunca responde. */
class DownLlm implements LlmDriver
{
    public int $calls = 0;

    public function chat(array $messages, array $tools): array
    {
        $this->calls++;

        throw new \RuntimeException('El modelo de IA no respondió (HTTP 502).');
    }

    public function toolMessages(array $results): array
    {
        return [['role' => 'tool', 'content' => 'principal']];
    }
}

/**
 * Modelo principal + respaldo: si el principal cae, el turno sigue con el respaldo
 * (y se queda ahí); si no hay respaldo válido, se comporta como siempre.
 */
class FallbackTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->loadPlugin('Aero.Connector');
        $this->migratePlugin('Aero.Connector');
        AgentRunner::$driverFactory = null;
    }

    public function tearDown(): void
    {
        AgentRunner::$driverFactory = null;
        Settings::set('models', []);
        Settings::set('agent_connector_id', null);
        parent::tearDown();
    }

    protected function connector(string $name, string $hint = 'ai_custom'): \Aero\Connector\Models\Connector
    {
        $c = new \Aero\Connector\Models\Connector();
        $c->name = $name;
        $c->provider_hint = $hint;
        $c->base_url = $hint === 'anthropic' ? 'https://api.anthropic.com' : 'https://example.test/v1';
        $c->is_enabled = true;
        $c->save();

        return $c;
    }

    protected function agent(?string $code, ?string $fallback): Staff
    {
        $s = new Staff();
        $s->fill(['name' => 'Ivan', 'slug' => 'ivan' . uniqid(), 'role' => 'Dev', 'kind' => 'ai', 'rarity' => 'r', 'is_active' => true, 'model_code' => $code, 'fallback_model_code' => $fallback]);
        $s->save();

        return $s->fresh();
    }

    public function testFallbackTakesOverWhenThePrimaryFailsAndStaysThere(): void
    {
        $down = new DownLlm();
        $spare = new FakeLlm([['text' => 'del respaldo'], ['text' => 'otra vez']]);
        $llm = new FallbackLlm($down, $spare);

        $this->assertSame('del respaldo', $llm->chat([], [])['text']);
        $this->assertTrue($llm->usedFallback());

        $llm->chat([], []);
        $this->assertSame(1, $down->calls, 'tras caer, el turno ya no vuelve a molestar al principal');
        $this->assertSame('tool', $llm->toolMessages([['id' => 'a', 'result' => []]])[0]['role']);
        $this->assertCount(2, $spare->seen);
    }

    public function testPrimaryIsUsedWhenItWorks(): void
    {
        $primary = new FakeLlm([['text' => 'principal']]);
        $spare = new FakeLlm([['text' => 'respaldo']]);
        $llm = new FallbackLlm($primary, $spare);

        $this->assertSame('principal', $llm->chat([], [])['text']);
        $this->assertFalse($llm->usedFallback());
        $this->assertCount(0, $spare->seen);
    }

    public function testAgentRunnerFinishesWithTheFallbackAndMarksTheMessage(): void
    {
        $staff = $this->agent(null, null);
        $reply = Message::create(['tenant_id' => 1, 'staff_id' => $staff->id, 'role' => 'assistant', 'status' => 'pending']);

        AgentRunner::run($reply, new FallbackLlm(new DownLlm(), new FakeLlm([['text' => 'Listo']])));
        $reply->refresh();

        $this->assertSame('done', $reply->status);
        $this->assertSame('Listo', $reply->content);
        $this->assertTrue($reply->meta['fallback']);
    }

    public function testAgentRunnerStillFailsCleanlyWhenBothDie(): void
    {
        $staff = $this->agent(null, null);
        $reply = Message::create(['tenant_id' => 1, 'staff_id' => $staff->id, 'role' => 'assistant', 'status' => 'pending']);

        AgentRunner::run($reply, new FallbackLlm(new DownLlm(), new DownLlm()));

        $this->assertSame('error', $reply->fresh()->status);
    }

    public function testDriverForBuildsAFallbackFromTheCatalog(): void
    {
        $gw = $this->connector('Gateway');
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('models', [
            ['code' => 'pro', 'kind' => 'code', 'model' => 'deepseek/deepseek-v4-pro'],
            ['code' => 'flash', 'kind' => 'code', 'model' => 'deepseek/deepseek-flash'],
        ]);

        $this->assertInstanceOf(FallbackLlm::class, AgentRunner::driverFor($this->agent('pro', 'flash')));
    }

    public function testNoFallbackWhenItIsMissingUnknownOrTheSameModel(): void
    {
        $gw = $this->connector('Gateway');
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('models', [['code' => 'pro', 'kind' => 'code', 'model' => 'deepseek/deepseek-v4-pro']]);

        $this->assertNotInstanceOf(FallbackLlm::class, AgentRunner::driverFor($this->agent('pro', null)));
        $this->assertNotInstanceOf(FallbackLlm::class, AgentRunner::driverFor($this->agent('pro', 'no_existe')));
        $this->assertNotInstanceOf(FallbackLlm::class, AgentRunner::driverFor($this->agent('pro', 'pro')), 'respaldarse con el mismo modelo no sirve');
    }

    public function testFallbackOfAnotherConnectorTypeIsIgnored(): void
    {
        $gw = $this->connector('Gateway');
        $claude = $this->connector('Claude', 'anthropic');
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('models', [
            ['code' => 'pro', 'kind' => 'code', 'model' => 'm/a'],
            ['code' => 'claude', 'kind' => 'text', 'model' => 'claude-x', 'connector_id' => $claude->id],
        ]);

        $this->assertNotInstanceOf(FallbackLlm::class, AgentRunner::driverFor($this->agent('pro', 'claude')), 'el historial no se traduce entre formatos');
    }

    public function testStaffFallbackOptionsOfferTheCatalogWithoutTheDefaultEntry(): void
    {
        Settings::set('models', [['code' => 'pro', 'label' => 'Pro', 'kind' => 'code', 'model' => 'a/b'], ['code' => 'img', 'label' => 'Img', 'kind' => 'image', 'model' => 'c/d']]);

        $options = $this->agent(null, null)->getFallbackModelCodeOptions();

        $this->assertSame('(sin respaldo)', $options['']);
        $this->assertArrayHasKey('pro', $options);
        $this->assertArrayNotHasKey('img', $options);
    }
}
