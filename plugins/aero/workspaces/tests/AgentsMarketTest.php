<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Components\AgentsMarket;
use Aero\Workspaces\Models\Staff;
use PluginTestCase;

/**
 * Mercado público: cualquiera ve el catálogo (sin prompt ni modelo) pero solo
 * una sesión con permiso puede contratar.
 */
class AgentsMarketTest extends PluginTestCase
{
    protected function staff(string $slug, array $extra = []): Staff
    {
        $s = new Staff();
        $s->fill($extra + ['name' => ucfirst($slug), 'slug' => $slug, 'role' => 'Rol', 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'web', 'is_active' => true,
            'system_prompt' => 'PROMPT-SECRETO', 'model_code' => 'MODELO-SECRETO']);
        $s->save();

        return $s;
    }

    public function testAnonymousSeesTheCatalogWithoutSecretsAndCannotHire(): void
    {
        $this->staff('ana');
        $this->staff('jefa', ['is_orchestrator' => true]);
        $this->staff('oculto', ['is_active' => false]);

        $c = new AgentsMarket();
        $market = $c->market();

        $slugs = array_column($market['agents'], 'slug');
        $this->assertContains('ana', $slugs);
        $this->assertNotContains('jefa', $slugs, 'la orquestadora no se vende');
        $this->assertNotContains('oculto', $slugs, 'los inactivos no se ven');
        $this->assertFalse($market['viewer']['logged']);
        $this->assertFalse($market['viewer']['can_hire']);
        $this->assertArrayNotHasKey('tenant_id', $market['viewer']);

        $json = json_encode($market);
        $this->assertStringNotContainsString('PROMPT-SECRETO', $json);
        $this->assertStringNotContainsString('MODELO-SECRETO', $json);

        try {
            $c->onHire();
            $this->fail('Sin sesión no se puede contratar.');
        }
        catch (\ApplicationException $e) {
            $this->assertStringContainsString('Inicia sesión', $e->getMessage());
        }

        $this->assertSame(0, \Aero\Workspaces\Models\Hire::count());
    }
}
