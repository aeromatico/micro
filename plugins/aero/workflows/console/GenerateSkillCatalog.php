<?php namespace Aero\Workflows\Console;

use Aero\Workflows\Classes\SkillCatalog;
use Illuminate\Console\Command;

/**
 * php artisan workflows:skill-catalog — regenera references/nodes.md del skill.
 */
class GenerateSkillCatalog extends Command
{
    protected $name = 'workflows:skill-catalog';
    protected $description = 'Regenera el catálogo de nodos del skill workflow-designer desde NodeRegistry.';

    public function handle(): int
    {
        $path = plugins_path('aero/workflows/skills/workflow-designer/references/nodes.md');

        file_put_contents($path, SkillCatalog::render() . "\n");

        $this->info("Catálogo escrito en {$path}");

        return 0;
    }
}
