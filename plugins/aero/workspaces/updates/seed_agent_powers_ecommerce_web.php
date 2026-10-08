<?php

use Aero\Workspaces\Models\Skill;
use October\Rain\Database\Updates\Seeder;

/**
 * Segunda tanda de poderes (agentes de eCommerce y Desarrollo Web): herramientas
 * de solo lectura para `clip` y `mon`, y el cuerpo de `res`. Misma regla que
 * seed_agent_powers: se fusiona con las herramientas existentes y el cuerpo solo
 * se escribe si está vacío.
 */
return new class extends Seeder
{
    public const POWERS = [
        'clip' => [
            'tools' => ['shop_products_list', 'sites_contact_get'],
            'body'  => "Para cortar clips de producto o promoción, mira el catálogo con `shop_products_list` y usa nombres y precios reales. Cierra cada clip con la llamada a la acción de `sites_contact_get` (WhatsApp o teléfono). No inventes datos.",
        ],
        'mon' => [
            'tools' => ['shop_products_list', 'sites_contact_get'],
            'body'  => "Antes de montar, confirma con `shop_products_list` qué productos aparecen y sus precios, y con `sites_contact_get` el dato de contacto del cierre. Entrega el plan de montaje (orden, tiempos, textos en pantalla).",
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

            if (trim((string) $skill->body) === '') {
                $skill->body = $power['body'];
            }

            $skill->save();
        }
    }
};
