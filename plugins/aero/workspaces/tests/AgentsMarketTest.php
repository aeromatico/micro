<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Components\AgentsMarket;
use Aero\Workspaces\Models\Staff;
use PluginTestCase;

/**
 * Catálogo público de la Oficina IA: cualquiera lo ve, sin prompt ni modelo, y
 * el componente no expone ninguna acción que escriba (contratar va por planes).
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

    public function testPublicCatalogHidesSecretsOrchestratorAndInactiveAgents(): void
    {
        $this->staff('ana');
        $this->staff('jefa', ['is_orchestrator' => true]);
        $this->staff('oculto', ['is_active' => false]);

        $market = (new AgentsMarket())->market();
        $slugs = array_column($market['agents'], 'slug');

        $this->assertContains('ana', $slugs);
        $this->assertNotContains('jefa', $slugs, 'la orquestadora no se vende');
        $this->assertNotContains('oculto', $slugs, 'los inactivos no se ven');

        $json = json_encode($market);
        $this->assertStringNotContainsString('PROMPT-SECRETO', $json);
        $this->assertStringNotContainsString('MODELO-SECRETO', $json);
        $this->assertSame([], array_filter(array_column($market['agents'], 'hired')), 'nadie lo «tiene contratado» en la vista pública');
    }

    public function testComponentExposesNoAjaxHandlers(): void
    {
        $own = array_filter((new \ReflectionClass(AgentsMarket::class))->getMethods(\ReflectionMethod::IS_PUBLIC), fn ($m) => $m->getDeclaringClass()->getName() === AgentsMarket::class);
        $handlers = array_filter(array_map(fn ($m) => $m->getName(), $own), fn ($n) => preg_match('/^on[A-Z]/', $n) === 1);

        $this->assertSame([], array_values($handlers), 'el catálogo público es solo lectura');
    }
}
