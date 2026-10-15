<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use October\Rain\Database\Updates\Seeder;

/**
 * Pone al día los perfiles del Mercado con lo que cada agente hace de verdad
 * (bio, rol, etiquetas, guía de uso, prompt y skills). Cada perfil solo se toca
 * mientras conserve los textos heredados de la carga inicial, así lo que el
 * superadmin edite después se respeta.
 *
 *  - Violeta pasa a ser la asistente de Google Sheets (skill `sheets-assistant`).
 *  - Renata Villca vuelve a ser la locutora: una versión previa la había
 *    convertido en asistente de Sheets por error de agente.
 *  - Mary (comercio electrónico) traía el prompt de «Directora de video».
 *  - Jaime y Hugo traían un nombre distinto al del perfil en su prompt.
 *  - Bruno no tenía radar de capacidades.
 */
return new class extends Seeder
{
    protected function inherited(Staff $s): bool
    {
        $p = (string) $s->system_prompt;

        return trim($p) === '' || str_contains($p, '[BORRADOR');
    }

    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        $this->sheets();
        $this->locutora();
        $this->ecommerce();
        $this->names();
        $this->radar();
    }

    protected function sheets(): void
    {
        $staff = Staff::where('slug', 'violeta')->first();
        $skill = Skill::whereNull('tenant_id')->where('slug', 'sheets-assistant')->first();

        if (!$staff || !$skill) {
            return;
        }

        $legacy = str_contains((string) $staff->role, 'Redactor') || $this->inherited($staff) || str_contains((string) $staff->system_prompt, 'Valerio');

        if ($legacy) {
            $seed = require __DIR__ . '/seed_sheets_agent.php';
            $staff->forceFill([
                'role'          => 'Especialista en Google Sheets',
                'category'      => 'info',
                'bio'           => 'Revisa, corrige, importa y exporta datos entre tu plataforma y tus hojas de Google Sheets, en las dos direcciones y siempre con tu visto bueno.',
                'tags'          => ['sheets', 'excel', 'datos', 'importar', 'exportar'],
                'guide'         => [
                    'Pega la URL de tu hoja y cuéntale qué quieres: corregirla, completarla, importarla o exportarla.',
                    'Antes de tocar nada te muestra el antes→después o una prueba, y espera tu «sí».',
                    'Necesitas conectar tu cuenta de Google con permiso de Sheets; las correcciones de celdas se pueden deshacer.',
                ],
                'system_prompt' => sprintf($seed::PROMPT, $staff->name),
            ])->save();

            // Sus skills de redacción ya no describen su trabajo.
            $old = Skill::whereNull('tenant_id')->whereIn('slug', ['guion', 'web'])->pluck('id')->all();
            $old && $staff->skills()->detach($old);
        }

        $staff->skills()->syncWithoutDetaching([$skill->id]);
    }

    protected function locutora(): void
    {
        $staff = Staff::where('slug', 'renata-villca')->first();

        if (!$staff || !str_contains((string) $staff->role, 'hojas de cálculo')) {
            return; // no fue convertida (o ya la restauraron a mano)
        }

        $staff->forceFill([
            'role'          => 'Locutora IA',
            'category'      => 'video',
            'bio'           => 'Locuta guiones con naturalidad en varios tonos. Marca pausas y énfasis donde el texto lo pide.',
            'tags'          => ['voz', 'locución', 'tono'],
            'guide'         => ['Entrégale el guion final con marcas de pausa.', 'Elige el tono: cálido, serio o enérgico.'],
            'system_prompt' => 'Eres Renata Villca, Locutora IA del equipo. Locuta guiones con naturalidad en varios tonos. Marca pausas y énfasis donde el texto lo pide. Responde en español, de forma clara, y entrega solo lo que se te pidió. [BORRADOR: prompt pendiente de revisión]',
            'is_active'     => false,
        ])->save();

        $own = Skill::whereNull('tenant_id')->whereIn('slug', ['voz', 'sub'])->pluck('id')->all();
        $staff->skills()->syncWithoutDetaching($own);

        $sheets = Skill::whereNull('tenant_id')->where('slug', 'sheets-assistant')->value('id');
        $sheets && $staff->skills()->detach([$sheets]);
    }

    protected function ecommerce(): void
    {
        $staff = Staff::where('slug', 'camila-soria')->first();

        if (!$staff || !str_contains((string) $staff->system_prompt, 'Directora de video')) {
            return;
        }

        $staff->forceFill([
            'tags'          => ['tienda', 'productos', 'precios', 'campañas', 'ecommerce'],
            'guide'         => [
                'Cuéntale qué quieres vender o mejorar: ella revisa tu catálogo y tus datos de contacto reales.',
                'Te propone textos de producto, ideas de campaña y orden del catálogo; no inventa precios ni productos.',
                'Para cargar o editar productos y páginas, pídeselo a Iván; para el CRM, a Silvia.',
            ],
            'system_prompt' => <<<TXT
Eres {$staff->name}, especialista en comercio electrónico del equipo. Ayudas a la persona a pensar su tienda: catálogo, precios, textos de producto y campañas, apoyándote en lo que ya publica.

Antes de proponer algo, mira el catálogo con `shop_products_list` y los datos de contacto con `sites_contact_get`.

Reglas que nunca rompes:
1. Nunca inventas productos, precios ni datos del negocio: si faltan, preguntas.
2. Haces de 1 a 3 preguntas por turno, concretas.
3. No cambias nada en la tienda: eres asesora. Para cargar o editar productos y páginas, la persona se lo pide a Iván; para el CRM, a Silvia.
4. Nunca dices que algo se hizo si ninguna herramienta lo confirmó.

Habla en español cercano, breve y claro.
TXT,
        ])->save();
    }

    protected function names(): void
    {
        foreach (['jaime', 'hugo'] as $slug) {
            $staff = Staff::where('slug', $slug)->first();

            if ($staff && preg_match('/^Eres ([^,]+),/u', (string) $staff->system_prompt, $m) && trim($m[1]) !== $staff->name) {
                $staff->system_prompt = preg_replace('/^Eres [^,]+,/u', 'Eres ' . $staff->name . ',', (string) $staff->system_prompt, 1);
                $staff->save();
            }
        }
    }

    protected function radar(): void
    {
        $bruno = Staff::where('slug', 'bruno')->first();

        if ($bruno && !array_filter((array) $bruno->capabilities)) {
            $bruno->capabilities = ['creatividad' => 52, 'viralidad' => 30, 'ejecucion' => 88, 'narrativa' => 60, 'estetica' => 36, 'eficiencia' => 90];
            $bruno->save();
        }
    }
};
