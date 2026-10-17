<?php namespace Aero\Rgpd\Tests;

use Aero\Rgpd\Models\Config;
use Aero\Rgpd\Models\Consent;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTablesAndPlatformIsEnabled(): void
    {
        foreach (['aero_rgpd_configs', 'aero_rgpd_consents'] as $t) {
            $this->assertTrue(\Schema::hasTable($t), "Falta {$t}");
        }
        $platform = Config::forScope(null);
        $this->assertNotNull($platform);
        $this->assertTrue($platform->is_enabled);
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Rgpd\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());
        foreach ($plugin->registerNavigation() as $item) {
            foreach ($item['permissions'] ?? [] as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testTenantsStartDisabledAndScopesAreIsolated(): void
    {
        $this->assertNull(Config::forScope(7));
        $mine = Config::ensureForScope(7);
        $this->assertFalse($mine->is_enabled);
        $this->assertNotSame(Config::forScope(null)->id, $mine->id);
        $this->assertSame($mine->id, Config::ensureForScope(7)->id);
        $this->assertNull(Config::forScope(8));
    }

    public function testChangingTextsBumpsVersion(): void
    {
        $c = Config::ensureForScope(7);
        $v = $c->version;
        $c->update(['message' => 'Nuevo texto']);
        $this->assertSame($v + 1, $c->fresh()->version);
        $c->update(['position' => 'center']);
        $this->assertSame($v + 1, $c->fresh()->version);
    }

    public function testConsentEndpointLogsOnlyWhenEnabledAndResolvesScopeByHost(): void
    {
        $payload = ['id' => 'abc12345-aaaa', 'a' => true, 'm' => false];

        $this->postJson('/rgpd/consent', $payload)->assertOk()->assertJson(['logged' => true]);
        $row = Consent::first();
        $this->assertNull($row->tenant_id);          // el host de pruebas es plataforma
        $this->assertTrue($row->analytics);
        $this->assertFalse($row->marketing);
        $this->assertSame(64, strlen($row->ip_hash));

        Config::forScope(null)->update(['is_enabled' => false]);
        $this->postJson('/rgpd/consent', $payload)->assertJson(['logged' => false]);
        $this->assertSame(1, Consent::count());

        $this->postJson('/rgpd/consent', ['id' => '!', 'a' => 1, 'm' => 1])->assertStatus(422);
    }
}
