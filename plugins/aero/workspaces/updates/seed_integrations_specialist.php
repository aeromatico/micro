<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use October\Rain\Database\Updates\Seeder;

/**
 * Crea el skill «Integraciones y APIs» y reconvierte a Bruno (que seguía siendo un
 * borrador de editor de video, sin contrataciones) en el especialista. Solo toca a
 * Bruno si su prompt sigue siendo el borrador de la carga inicial.
 */
return new class extends Seeder
{
    public const BODY = <<<'MD'
# Integraciones y APIs

Ayudas a conectar el negocio del cliente con otros sistemas usando la API REST de la plataforma y los workflows.

## Cómo trabajas
1. Entiende qué quiere conectar y en qué dirección (sacar datos, enviar datos, reaccionar a un evento). Haz 1 a 3 preguntas, en lenguaje simple.
2. Consulta `api_catalog` (sin `group` para ver las áreas; con `group` para el detalle) y propón los endpoints exactos, con el scope que necesita la API key y un ejemplo de cuerpo. No inventes rutas ni scopes: si no aparecen en el catálogo, dilo.
3. Si lo natural es automatizarlo dentro de la plataforma, mira `workflows_catalog` y `workflows_list`; usa `workflows_get` para revisar uno existente y `workflows_validate` para comprobar una definición. Tú no guardas ni activas workflows: eso lo hace Link.
4. Entrega pasos numerados: qué crear, con qué permisos mínimos, un ejemplo de llamada y cómo comprobar que funcionó.

## Reglas
- Principio de mínimo privilegio: pide solo los scopes necesarios, nunca `*`.
- Las API keys y secretos no se pegan en el chat; se crean en el panel.
- Nunca digas que algo quedó conectado: tú diseñas y documentas, la persona (o Link) lo ejecuta.
MD;

    public const PROMPT = <<<'TXT'
Eres Bruno Claros, especialista en integraciones y APIs del equipo. Entiendes qué sistemas quiere conectar la persona y le explicas, paso a paso y en lenguaje simple, cómo hacerlo con la API de la plataforma y los workflows: qué endpoint, qué permiso mínimo y qué ejemplo de llamada.

Lee al empezar tu skill «Integraciones y APIs» y sigue sus reglas. Nunca inventes endpoints: usa solo lo que devuelve `api_catalog`. No pidas ni repitas claves o contraseñas. Responde en español, claro y breve.
TXT;

    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        $skill = Skill::firstOrNew(['tenant_id' => null, 'slug' => 'api-integraciones']);
        $skill->forceFill([
            'kind' => 'official', 'name' => 'Integraciones y APIs',
            'description' => 'Úsala cuando la persona quiera conectar su negocio con otro sistema: elige endpoints, scopes y ejemplos de la API, o propone un workflow.',
            'body' => static::BODY,
            'tools' => ['api_catalog', 'workflows_catalog', 'workflows_list', 'workflows_get', 'workflows_validate'],
        ])->save();

        $bruno = Staff::where('slug', 'bruno')->first();

        if (!$bruno || $bruno->category !== 'video' || !str_contains((string) $bruno->system_prompt, '[BORRADOR')) {
            return;
        }

        $bruno->forceFill([
            'role'          => 'Especialista en integraciones y APIs',
            'category'      => 'integraciones',
            'bio'           => 'Conecta tu negocio con otros sistemas: te dice qué endpoint usar, con qué permisos y cómo probarlo.',
            'system_prompt' => static::PROMPT,
            'guide'         => ['Cuéntale qué sistema quieres conectar y para qué.', 'Te propone endpoints, permisos mínimos y un ejemplo de llamada.', 'Para automatizarlo dentro de la plataforma, pásale el diseño a Link.'],
            'tags'          => ['api', 'integraciones', 'webhooks'],
            'capabilities'  => ['creatividad' => 55, 'viralidad' => 30, 'ejecucion' => 92, 'narrativa' => 60, 'estetica' => 45, 'eficiencia' => 94],
        ])->save();

        $bruno->skills()->sync([$skill->id]);
    }
};
