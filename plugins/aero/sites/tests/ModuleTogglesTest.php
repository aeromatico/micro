<?php namespace Aero\Sites\Tests;

use Aero\Sites\Classes\ModuleCatalog;
use Aero\Sites\Models\Plan;
use Aero\Sites\Models\Tenant;
use PluginTestCase;

class ModuleTogglesTest extends PluginTestCase
{
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    public function setUp(): void
    {
        parent::setUp();

        \Schema::create('aero_sites_tenants', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('niche_type')->nullable();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->json('disabled_plugins')->nullable();
            $t->timestamp('modules_onboarded_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });

        ModuleCatalog::$codesForTesting = ['Aero.Crm', 'Aero.Gym', 'Aero.Hello', 'Aero.Chatbots', 'Aero.Pos', 'Aero.Shop', 'Aero.Pay', 'Aero.Nuevo'];
    }

    public function tearDown(): void
    {
        ModuleCatalog::$codesForTesting = null;
        parent::tearDown();
    }

    protected function tenant(?array $planPlugins, string $niche = 'generic', ?array $disabled = null): Tenant
    {
        $id = \DB::table('aero_sites_tenants')->insertGetId([
            'name' => 'T', 'niche_type' => $niche,
            'disabled_plugins' => $disabled === null ? null : json_encode($disabled),
        ]);
        $t = Tenant::find($id);
        $plan = new Plan;
        $plan->plugins = $planPlugins;
        $t->setRelation('plan', $plan);

        return $t;
    }

    public function testEncendidoCruzaPlanYSwitch(): void
    {
        $t = $this->tenant(['Aero.Crm', 'Aero.Gym'], 'generic', ['Aero.Gym']);

        $this->assertTrue($t->pluginEnabled('Aero.Crm'));
        $this->assertFalse($t->pluginEnabled('Aero.Gym'), 'apagado por el tenant');
        $this->assertFalse($t->pluginEnabled('Aero.Shop'), 'fuera del plan');
        $this->assertTrue($t->pluginEnabled('Aero.Credits'), 'siempre permitido');

        $sinPlan = $this->tenant(null);
        $this->assertTrue($sinPlan->pluginEnabled('Aero.Shop'), 'plan sin restricción y nada apagado');
    }

    public function testPreseleccionEsencialesYRubroDentroDelPlan(): void
    {
        $t = $this->tenant(['Aero.Crm', 'Aero.Gym', 'Aero.Hello', 'Aero.Shop'], 'gimnasios');
        $defaults = ModuleCatalog::defaultsFor($t);
        sort($defaults);

        // Pay y Notify son esenciales pero el plan no los incluye; Nuevo no tiene META.
        $this->assertSame(['Aero.Crm', 'Aero.Gym', 'Aero.Hello'], $defaults);
    }

    public function testEncenderArrastraDependenciasYApagarArrastraDependientes(): void
    {
        $t = $this->tenant(null, 'generic', ['Aero.Pos', 'Aero.Shop', 'Aero.Hello', 'Aero.Chatbots']);

        $changed = ModuleCatalog::toggle($t, 'Aero.Pos', true);
        sort($changed);
        $this->assertSame(['Aero.Pos', 'Aero.Shop'], $changed);
        $this->assertTrue(Tenant::find($t->id)->pluginEnabled('Aero.Shop'), 'persistido');

        $changed = ModuleCatalog::toggle($t, 'Aero.Shop', false);
        sort($changed);
        $this->assertSame(['Aero.Pos', 'Aero.Shop'], $changed);
        $this->assertFalse($t->pluginEnabled('Aero.Pos'));
    }

    public function testNoSePuedeEncenderLoQueElPlanNoIncluye(): void
    {
        $t = $this->tenant(['Aero.Crm']);

        $this->expectException(\ApplicationException::class);
        ModuleCatalog::toggle($t, 'Aero.Gym', true);
    }

    public function testNoSePuedeCambiarUnPluginNoGestionable(): void
    {
        $this->expectException(\ApplicationException::class);
        ModuleCatalog::toggle($this->tenant(null), 'Aero.Sites', false);
    }

    public function testBienvenidaGuardaEleccionYMarcaOnboarded(): void
    {
        $t = $this->tenant(null);
        $this->assertTrue($t->needsModulesWelcome());

        ModuleCatalog::applySelection($t, ['Aero.Chatbots', 'Aero.Crm', 'Aero.Inventado']);

        $fresh = Tenant::find($t->id);
        $this->assertFalse($fresh->needsModulesWelcome());
        $this->assertTrue($fresh->pluginEnabled('Aero.Hello'), 'dependencia de Chatbots');
        $this->assertTrue($fresh->pluginEnabled('Aero.Crm'));
        $this->assertFalse($fresh->pluginEnabled('Aero.Gym'));
        $this->assertFalse($fresh->pluginEnabled('Aero.Nuevo'));
    }

    public function testEventoDeCambio(): void
    {
        $seen = [];
        \Event::listen('aero.sites.pluginToggled', function ($tenant, $code, $on) use (&$seen) {
            $seen[] = [$code, $on];
        });

        $t = $this->tenant(null);
        ModuleCatalog::toggle($t, 'Aero.Gym', false);
        ModuleCatalog::toggle($t, 'Aero.Gym', true);

        $this->assertSame([['Aero.Gym', false], ['Aero.Gym', true]], $seen);
    }
}
