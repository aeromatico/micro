<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use October\Rain\Database\Updates\Seeder;

/**
 * Le da a Iván (slug `ivan`, desarrollo web) su trabajo real sobre el sitio y la
 * tienda del cliente: el skill «Constructor web y tienda» (herramientas web_* de
 * Classes\WebTools + protocolo de preguntas) y su prompt. El skill se refresca
 * siempre desde `skills/web-builder/`; el prompt solo se reemplaza si sigue siendo
 * el borrador heredado de la carga inicial (era el de «Analista de datos»).
 */
return new class extends Seeder
{
    public const TOOLS = [
        'web_context', 'web_blocks_catalog', 'web_pages', 'web_page_save', 'web_page_publish', 'web_seo_set', 'web_contact_set',
        'web_collections', 'web_collection_save', 'web_products', 'web_product_save', 'web_products_create', 'web_product_status',
        'web_undo', 'web_changes',
    ];

    public const PROMPT = <<<'TXT'
Eres %s, experto en desarrollo web del equipo. Conviertes lo que el negocio necesita en un sitio y una tienda que funcionan: páginas, SEO, contacto, colecciones y productos, para el cliente con el que hablas.

Sigue al pie de la letra el skill «Constructor web y tienda» y léelo al empezar.

Reglas que nunca rompes:
1. Antes de preguntar, mira lo que ya existe con `web_context`; nunca preguntes lo que ya sabes.
2. Preguntas precisas: máximo 4 por turno, concretas, con opciones cuando se pueda. No construyes mientras falte un dato esencial.
3. Acuerdas el plan con la persona y esperas su «sí» antes de guardar nada.
4. No inventas datos del negocio (precios, testimonios, cifras, direcciones). Si faltan, preguntas.
5. Lo nuevo queda en borrador. Publicar, activar o cambiar algo que ya está en vivo solo con confirmación explícita.
6. Nunca dices que algo se hizo si una herramienta no lo confirmó; los enlaces los das tal cual los devolvió.

Habla en español cercano, breve y claro.
TXT;

    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        $staff = Staff::where('slug', 'ivan')->first();

        if (!$staff) {
            return;
        }

        $raw = @file_get_contents(dirname(__DIR__) . '/skills/web-builder/SKILL.md');

        if ($raw === false) {
            return;
        }

        $description = 'Úsala cuando la persona pida crear o cambiar páginas, SEO, contacto, colecciones o productos.';

        if (preg_match('/^---\R(.*?)\R---\R/s', $raw, $m)) {
            if (preg_match('/^description:\s*(.+)$/m', $m[1], $d)) {
                $description = trim($d[1]);
            }

            $raw = substr($raw, strlen($m[0]));
        }

        $skill = Skill::firstOrNew(['tenant_id' => null, 'slug' => 'web-builder']);
        $skill->forceFill([
            'kind' => 'official', 'name' => 'Constructor web y tienda', 'description' => mb_substr($description, 0, 1000),
            'body' => trim($raw), 'tools' => static::TOOLS,
        ])->save();

        $prompt = trim((string) $staff->system_prompt);

        if ($prompt === '' || str_contains($prompt, '[BORRADOR')) {
            $staff->system_prompt = sprintf(static::PROMPT, $staff->name);
        }

        if (empty($staff->guide)) {
            $staff->guide = [
                'Cuéntale qué necesitas (una página, el catálogo, el SEO): te hará las preguntas justas, sin repetir lo que ya sabe de tu negocio.',
                'Confirma el plan con un «sí» claro: hasta entonces no guarda nada.',
                'Todo lo nuevo queda en borrador; tú decides cuándo publicarlo y puedes deshacer cualquier cambio.',
            ];
        }

        $staff->save();
        $staff->skills()->syncWithoutDetaching([$skill->id]);
    }
};
