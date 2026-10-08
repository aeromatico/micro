<?php

use Aero\Workspaces\Models\Skill;
use October\Rain\Database\Updates\Seeder;

/**
 * Da herramientas reales (solo lectura del negocio del cliente) a los skills de
 * los agentes activos, para que dejen de solo conversar. Se FUSIONAN con las que
 * el skill ya tenga y el cuerpo solo se escribe si está vacío, así no pisa lo que
 * edite el superadmin. Las herramientas deben existir en AiToolRegistry; las que
 * no, el motor las ignora.
 */
return new class extends Seeder
{
    public const POWERS = [
        'web' => [
            'tools' => ['sites_landing_get', 'sites_contact_get'],
            'body'  => "Antes de investigar o escribir sobre el negocio, consulta lo que ya publica:\n- `sites_landing_get` (sin slug = inicio, o el slug de una página) para ver su texto y su tono actuales.\n- `sites_contact_get` para los datos de contacto reales.\n\nNunca inventes datos del negocio: si una herramienta no los devuelve, dilo y pregunta.",
        ],
        'seo' => [
            'tools' => ['sites_landing_get', 'shop_products_list'],
            'body'  => "Para proponer títulos, descripciones y etiquetas:\n1. Lee la página real con `sites_landing_get` y parte de su contenido, no de supuestos.\n2. Si el negocio vende, usa `shop_products_list` para nombres y precios reales.\n\nEntrega las propuestas como texto para que la persona las revise; no publicas nada.",
        ],
        'guion' => [
            'tools' => ['sites_contact_get', 'shop_products_list'],
            'body'  => "Antes de escribir el guion consulta al negocio:\n- `shop_products_list` para los productos que conviene mencionar, con precio real.\n- `sites_contact_get` para la llamada a la acción (WhatsApp, teléfono, dirección).\n\nNo inventes precios ni datos de contacto.",
        ],
        'img' => [
            'tools' => ['shop_products_list'],
            'body'  => "Si el brief habla de productos, mira el catálogo con `shop_products_list` para describir los reales (nombre, descripción) en lugar de imaginarlos. Devuelve el brief visual listo para generar.",
        ],
        'team-orchestration' => [
            'tools' => ['workspaces_agent', 'workspaces_tasks', 'workspaces_skills'],
            'body'  => null,
        ],
    ];

    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        foreach (static::POWERS as $slug => $power) {
            $skill = Skill::whereNull('tenant_id')->where('slug', $slug)->first();

            if (!$skill) {
                continue;
            }

            $skill->tools = array_values(array_unique(array_merge((array) $skill->tools, $power['tools'])));

            if ($power['body'] && trim((string) $skill->body) === '') {
                $skill->body = $power['body'];
            }

            $skill->save();
        }
    }
};
