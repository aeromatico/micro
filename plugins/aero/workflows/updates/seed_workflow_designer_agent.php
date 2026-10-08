<?php

use Aero\Workflows\Classes\BuilderTools;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Instala (o actualiza) al agente «Link, diseñador de flujos» en Aero.Workspaces
 * con sus tres skills, tomados de `skills/workflow-designer/` (la fuente de
 * verdad en el repo). Soft: sin Workspaces instalado no hace nada.
 *
 * Qué se pisa y qué no:
 *  - Los 3 skills oficiales se refrescan siempre (descripción, cuerpo y tools):
 *    son documentación generada del repo, no se editan a mano.
 *  - Link se crea INACTIVO si no existe (como el resto del catálogo, lo activa
 *    el superadmin). Si ya existe, solo se reemplaza el prompt de sistema cuando
 *    sigue siendo el borrador de la carga inicial (`[BORRADOR`) o está vacío, y
 *    solo se rellenan guía y etiquetas vacías: lo que el superadmin haya editado
 *    se conserva.
 */
return new class extends Seeder
{
    public const PROMPT = <<<'TXT'
Eres Link, diseñador de flujos del equipo. Trabajas CON la persona, no por ella: primero entiendes lo que quiere lograr, luego le propones el flujo en pasos simples, lo acuerdan, y solo entonces lo construyes.

Sigue al pie de la letra el skill «Diseño de flujos» (sus cinco fases: descubrir, proponer, acordar, construir, entregar) y consulta «Catálogo de nodos» y «Patrones de flujos».

Reglas que nunca rompes:
1. Haces de 1 a 3 preguntas por turno, en lenguaje cotidiano y con opciones cuando se pueda. No uses jerga (nodo, handle, grafo) salvo que la persona la use.
2. No construyes sin un «sí» claro al plan que le mostraste. Un silencio no es un sí.
3. No inventas nodos, campos, salidas ni ids. Antes de armar cada nodo pide su detalle con workflows_catalog; los datos del cliente (cuentas, Connectors, flujos existentes) salen de workflows_context y workflows_list.
4. Validas con workflows_validate y corriges tú mismo hasta que pase; no le pases errores técnicos a la persona.
5. Guardas solo con workflows_save_draft, enviando como agreed_plan el plan que ella aprobó. Nunca publicas ni activas nada: lo revisa y publica una persona.
6. Si algo que pide no se puede hacer en el editor (por ejemplo, esperar la respuesta del cliente dentro del mismo flujo), lo dices con claridad y le ofreces la mejor alternativa real.
7. Si no tienes las herramientas, haces igual las fases de conversación y entregas al final el JSON indicado en el skill.

Habla en español cercano (tuteo), breve y claro. Al entregar, di qué creaste, qué debe revisar antes de publicar y cómo probarlo.
TXT;

    protected const SKILLS = [
        'workflow-designer' => [
            'name' => 'Diseño de flujos',
            'file' => 'SKILL.md',
            'tools' => ['workflows_context', 'workflows_list', 'workflows_get', 'workflows_catalog', 'workflows_validate', 'workflows_save_draft', 'workflows_update', 'workflows_revert'],
        ],
        'workflow-nodes' => [
            'name' => 'Catálogo de nodos de workflows',
            'file' => 'references/nodes.md',
            'description' => 'Lista de nodos de Aero.Workflows, sus salidas y sus campos. Úsala antes de escribir cualquier arista o campo de un flujo (con herramientas, manda workflows_catalog, que es el catálogo vivo).',
        ],
        'workflow-patterns' => [
            'name' => 'Patrones de flujos',
            'file' => 'references/patterns.md',
            'description' => 'Patrones probados y validados: consulta de catálogo, decisión, respuesta por palabra, menú con botones, menú desplegable, ubicación y cobertura. Úsala para empezar un flujo común en vez de desde cero.',
        ],
    ];

    public function run(): void
    {
        if (!class_exists(\Aero\Workspaces\Models\Staff::class) || !Schema::hasTable('aero_workspaces_staff') || !Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        $dir = dirname(__DIR__) . '/skills/workflow-designer/';
        $skillIds = [];

        foreach (static::SKILLS as $slug => $def) {
            $raw = @file_get_contents($dir . $def['file']);

            if ($raw === false) {
                continue;
            }

            $description = $def['description'] ?? null;

            if (preg_match('/^---\R(.*?)\R---\R/s', $raw, $m)) {
                if (!$description && preg_match('/^description:\s*(.+)$/m', $m[1], $d)) {
                    $description = trim($d[1]);
                }

                $raw = substr($raw, strlen($m[0]));
            }

            $skill = \Aero\Workspaces\Models\Skill::firstOrNew(['tenant_id' => null, 'slug' => $slug]);
            $skill->forceFill([
                'kind'        => 'official',
                'name'        => $def['name'],
                'description' => mb_substr((string) $description, 0, 1000),
                'body'        => trim($raw),
                'tools'       => $def['tools'] ?? null,
            ])->save();

            $skillIds[] = $skill->id;
        }

        $this->agent($skillIds);
    }

    protected function agent(array $skillIds): void
    {
        $staff = \Aero\Workspaces\Models\Staff::firstOrNew(['slug' => 'link']);
        $isNew = !$staff->exists;

        $guide = [
            'Cuéntale qué quieres lograr, no cómo hacerlo: él te hace las preguntas.',
            'Ten a mano tus textos y, si es WhatsApp, qué cuenta quieres usar.',
            'Confirma el plan con un «sí» claro: hasta entonces no construye nada.',
            'Lo que entrega queda en borrador: tú lo revisas, pruebas y publicas.',
        ];

        if ($isNew) {
            $connector = class_exists(\Aero\Connector\Models\Connector::class)
                ? \Aero\Connector\Models\Connector::where('type', 'yepapi')->where('is_enabled', true)->value('id')
                : null;

            $staff->fill([
                'name' => 'Link', 'role' => 'Diseñador de flujos', 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'automatizacion',
                'bio' => 'Convierte tus procesos en flujos automáticos. Te hace las preguntas clave, acuerda contigo el plan y deja el borrador listo para que lo revises y publiques.',
                'capabilities' => ['creatividad' => 70, 'viralidad' => 40, 'ejecucion' => 92, 'narrativa' => 55, 'estetica' => 50, 'eficiencia' => 88],
                'connector_id' => $connector, 'avatar' => '', 'is_active' => false, 'sort_order' => 13, 'hire_fee' => 0,
            ]);
        }

        $prompt = trim((string) $staff->system_prompt);

        if ($isNew || $prompt === '' || str_contains($prompt, '[BORRADOR')) {
            $staff->system_prompt = static::PROMPT;
        }

        if (!$staff->guide) {
            $staff->guide = $guide;
        }

        if (!$staff->tags) {
            $staff->tags = ['flujos', 'automatización', 'WhatsApp', 'menús y botones'];
        }

        $staff->save();
        $staff->skills()->syncWithoutDetaching($skillIds);
    }
};
