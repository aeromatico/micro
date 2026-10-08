<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use October\Rain\Database\Updates\Seeder;

/**
 * Le da a la orquestadora su trabajo real: el skill «Orquestación del equipo» (con
 * las herramientas para ver el equipo, sugerir contrataciones y delegar) y su
 * prompt. El skill se refresca siempre desde `skills/team-orchestration/` (documentación
 * del repo); el prompt solo se reemplaza si sigue siendo el borrador de la carga
 * inicial, así lo que el superadmin edite se conserva.
 */
return new class extends Seeder
{
    public const PROMPT = <<<'TXT'
Eres %s, la orquestadora del equipo. La persona habla contigo: tú entiendes lo que quiere lograr, ves quién del equipo puede hacerlo de verdad, le propones un plan, lo acuerdan y reparto cada paso a los agentes. Respondes por el resultado.

Sigue al pie de la letra el skill «Orquestación del equipo» y léelo al empezar.

Reglas que nunca rompes:
1. Haces de 1 a 3 preguntas por turno, en lenguaje cotidiano. No interrogues.
2. No delegas sin un «sí» claro al plan que le mostraste.
3. Solo los agentes reales (live) trabajan de verdad; con los demás eres honesta: dices qué parte aún no se puede hacer, nunca finges.
4. Nunca digas que algo se hizo si ninguna herramienta lo confirmó. Los enlaces los entregas tal cual los devolvió la herramienta.
5. Nunca publicas ni activas nada: lo que se crea queda como borrador y lo revisa la persona.

Habla en español cercano, breve y claro.
TXT;

    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        $orchestrator = Staff::orchestrator();

        if (!$orchestrator) {
            return;
        }

        $raw = @file_get_contents(dirname(__DIR__) . '/skills/team-orchestration/SKILL.md');

        if ($raw === false) {
            return;
        }

        $description = 'Úsala siempre que la persona te pida algo: entiende, ve el equipo, propone un plan, lo acuerda y reparte los pasos entre los agentes.';

        if (preg_match('/^---\R(.*?)\R---\R/s', $raw, $m)) {
            if (preg_match('/^description:\s*(.+)$/m', $m[1], $d)) {
                $description = trim($d[1]);
            }

            $raw = substr($raw, strlen($m[0]));
        }

        $skill = Skill::firstOrNew(['tenant_id' => null, 'slug' => 'team-orchestration']);
        $skill->forceFill([
            'kind' => 'official', 'name' => 'Orquestación del equipo', 'description' => mb_substr($description, 0, 1000),
            'body' => trim($raw), 'tools' => ['workspaces_team', 'workspaces_market', 'workspaces_agent', 'workspaces_tasks', 'workspaces_skills', 'workflows_list', 'workflows_get', 'team_delegate'],
        ])->save();

        $prompt = trim((string) $orchestrator->system_prompt);

        if ($prompt === '' || str_contains($prompt, '[BORRADOR')) {
            $orchestrator->system_prompt = sprintf(static::PROMPT, $orchestrator->name);
        }

        if (!$orchestrator->guide) {
            $orchestrator->guide = ['Cuéntale qué quieres lograr: ella te hace las preguntas y arma el plan.', 'Confirma el plan con un «sí» claro: hasta entonces no reparte nada.', 'Lo que su equipo crea queda en borrador para que lo revises.'];
        }

        $orchestrator->save();
        $orchestrator->skills()->syncWithoutDetaching([$skill->id]);
    }
};
