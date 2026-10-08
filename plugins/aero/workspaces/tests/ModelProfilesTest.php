<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\AgentRunner;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;
use PluginTestCase;

/**
 * Catálogo de modelos especializados (Ajustes) y cómo lo elige cada agente.
 */
class ModelProfilesTest extends PluginTestCase
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
        Settings::set('models', []);
        Settings::set('agent_connector_id', null);
        Settings::set('agent_model', null);
        parent::tearDown();
    }

    protected function connector(string $name, bool $enabled = true): \Aero\Connector\Models\Connector
    {
        $c = new \Aero\Connector\Models\Connector();
        $c->name = $name;
        $c->provider_hint = 'ai_custom';
        $c->base_url = 'https://example.test/v1';
        $c->is_enabled = $enabled;
        $c->save();

        return $c;
    }

    protected function agent(?string $code = null): Staff
    {
        $s = new Staff();
        $s->fill(['name' => 'Ivan', 'slug' => 'ivan' . uniqid(), 'role' => 'Dev', 'kind' => 'ai', 'rarity' => 'r', 'is_active' => true, 'model_code' => $code]);
        $s->save();

        return $s->fresh();
    }

    /** Lo que el LlmDriver le pedirá al conector. */
    protected function chosen(Staff $staff): array
    {
        $llm = AgentRunner::driverFor($staff);
        $ref = new \ReflectionObject($llm);

        return [$ref->getProperty('connector')->getValue($llm)->id, $ref->getProperty('model')->getValue($llm)];
    }

    public function testCatalogKeepsOnlyActiveWellFormedRows(): void
    {
        Settings::set('models', [
            ['code' => 'code_max', 'label' => 'Máx', 'kind' => 'code', 'model' => 'deepseek/deepseek-v4-pro', 'is_active' => true],
            ['code' => 'off', 'label' => 'Apagado', 'kind' => 'code', 'model' => 'x/y', 'is_active' => false],
            ['code' => '', 'kind' => 'code', 'model' => 'x/y'],
            ['code' => 'sin_modelo', 'kind' => 'code', 'model' => ''],
            ['code' => 'raro', 'kind' => 'inventado', 'model' => 'a/b'],
        ]);

        $models = Settings::models();

        $this->assertSame(['code_max', 'raro'], array_keys($models));
        $this->assertSame('text', $models['raro']['kind'], 'un tipo desconocido cae a texto');
        $this->assertSame('Máx', $models['code_max']['label']);
    }

    public function testModelOfKindFindsTheFirstActiveOne(): void
    {
        Settings::set('models', [
            ['code' => 'v1', 'kind' => 'video', 'model' => 'openai/sora-2'],
            ['code' => 'i1', 'kind' => 'image', 'model' => 'openai/gpt-image-1'],
        ]);

        $this->assertSame('openai/gpt-image-1', Settings::modelOfKind('image')['model']);
        $this->assertNull(Settings::modelOfKind('audio'));
    }

    public function testStaffOptionsOnlyOfferChatCapableModels(): void
    {
        Settings::set('models', [
            ['code' => 'code_max', 'label' => 'Máx', 'kind' => 'code', 'model' => 'a/b'],
            ['code' => 'img', 'label' => 'Img', 'kind' => 'image', 'model' => 'c/d'],
        ]);

        $options = $this->agent()->getModelCodeOptions();

        $this->assertArrayHasKey('code_max', $options);
        $this->assertArrayNotHasKey('img', $options, 'un modelo de imagen no conversa');
    }

    public function testAgentUsesItsProfileModelOnTheSettingsConnector(): void
    {
        $gw = $this->connector('Gateway');
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('models', [['code' => 'code_max', 'kind' => 'code', 'model' => 'deepseek/deepseek-v4-pro']]);

        $this->assertSame([$gw->id, 'deepseek/deepseek-v4-pro'], $this->chosen($this->agent('code_max')));
    }

    public function testAgentWithoutProfileUsesTheDefaultModel(): void
    {
        $gw = $this->connector('Gateway');
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('agent_model', 'openai/gpt-4.1');

        $this->assertSame([$gw->id, 'openai/gpt-4.1'], $this->chosen($this->agent()));
    }

    public function testProfileConnectorOverridesTheSettingsOne(): void
    {
        $gw = $this->connector('Gateway');
        $other = $this->connector('Otro');
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('models', [['code' => 'especial', 'kind' => 'code', 'model' => 'm/x', 'connector_id' => $other->id]]);

        $this->assertSame([$other->id, 'm/x'], $this->chosen($this->agent('especial')));
    }

    public function testDisabledGatewayFallsBackWithoutSendingAGatewayModelId(): void
    {
        $first = $this->connector('Directo');
        $gw = $this->connector('Gateway', false);
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('agent_model', 'openai/gpt-4.1');
        Settings::set('models', [['code' => 'code_max', 'kind' => 'code', 'model' => 'deepseek/deepseek-v4-pro']]);

        [$id, $model] = $this->chosen($this->agent('code_max'));

        $this->assertSame($first->id, $id, 'con el gateway apagado se usa el primer conector activo');
        $this->assertNull($model, 'el ID del gateway no se manda a otro proveedor');
    }

    public function testNonChatProfileIsIgnoredByAgents(): void
    {
        $gw = $this->connector('Gateway');
        Settings::set('agent_connector_id', $gw->id);
        Settings::set('models', [['code' => 'img', 'kind' => 'image', 'model' => 'openai/gpt-image-1']]);

        $this->assertSame([$gw->id, null], $this->chosen($this->agent('img')));
    }
}
