<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use October\Rain\Database\Updates\Seeder;

/**
 * Ordena las especialidades: la producción multimedia (guiones, clips, montaje y música) es de Gus, no de Mary, que
 * se especializa en comercio electrónico. Los tres skills de video venían heredados de la carga inicial y a Mary le
 * daban de rebote sus herramientas de catálogo y contacto, así que se le crea su propio skill «Asesoría de tienda y
 * catálogo» con esas mismas herramientas. El perfil de Gus (rol, etiquetas, guía y prompt, que era un borrador
 * heredado de «Tomás Aguilar, Presentaciones») se actualiza una sola vez.
 */
return new class extends Seeder
{
    public const SHOP_TOOLS = ['shop_products_list', 'sites_contact_get'];

    public const GUS_PROMPT = <<<'TXT'
Eres %s, productor multimedia del equipo. Ayudas a la persona a convertir una idea en piezas de video y presentaciones: guiones con escenas y tiempos, prompts para generar clips, planes de montaje, música de fondo y presentaciones claras. Te apoyas en lo que su negocio ya publica: mira el catálogo con `shop_products_list` y los datos de contacto con `sites_contact_get`.

Reglas que nunca rompes:
1. Nunca inventas productos, precios ni datos del negocio: si faltan, preguntas (1 a 3 preguntas por turno, concretas: objetivo, público, duración, formato).
2. Entregas piezas listas para usar: guion por escenas con tiempos, prompts de video con estilo y duración, lista de montaje con orden y transiciones.
3. Con las herramientas que tienes lees el negocio; no generas ni renderizas video tú. No digas que un clip, un audio o un montaje «ya está» si ninguna herramienta lo confirmó: di que lo entregas como guion, prompt o plan.
4. Para voz y subtítulos, di que lo hace Renata cuando esté disponible; para imágenes, a Paola.

Habla en español cercano, breve y claro.
TXT;

    public function run(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        $mary = Staff::where('name', 'Mary')->first();
        $gus = Staff::where('slug', 'gus')->first();

        if (!$mary || !$gus) {
            return;
        }

        // 1) Mary conserva sus herramientas de catálogo con un skill propio.
        $raw = @file_get_contents(dirname(__DIR__) . '/skills/shop-advisor/SKILL.md');
        $advisor = null;

        if ($raw !== false) {
            $description = 'Úsala cuando la persona quiera pensar su tienda online.';

            if (preg_match('/^---\R(.*?)\R---\R/s', $raw, $m)) {
                if (preg_match('/^description:\s*(.+)$/m', $m[1], $d)) {
                    $description = trim($d[1]);
                }

                $raw = substr($raw, strlen($m[0]));
            }

            $advisor = Skill::firstOrNew(['tenant_id' => null, 'slug' => 'shop-advisor']);
            $advisor->forceFill([
                'kind' => 'official', 'name' => 'Asesoría de tienda y catálogo', 'description' => mb_substr($description, 0, 1000),
                'body' => trim($raw), 'tools' => static::SHOP_TOOLS,
            ])->save();
            $mary->skills()->syncWithoutDetaching([$advisor->id]);
        }

        // 2) Los skills de producción multimedia pasan de Mary a Gus (solo si ya tiene el reemplazo).
        $media = Skill::whereNull('tenant_id')->whereIn('slug', ['guion', 'clip', 'mon', 'mus'])->pluck('id')->all();
        $gus->skills()->syncWithoutDetaching($media);

        if ($advisor) {
            $mary->skills()->detach(array_diff($media, [$advisor->id]));
        }

        // 3) Perfil de Mary (ecommerce) y de Gus (multimedia), una sola vez.
        $tags = array_values(array_unique(array_merge((array) $mary->tags, ['ecommerce', 'catálogo', 'precios', 'campañas'])));
        $mary->forceFill(['tags' => $tags])->save();

        $prompt = (string) $gus->system_prompt;

        if (str_contains($prompt, 'BORRADOR') || str_contains($prompt, 'Tomás Aguilar')) {
            $gus->system_prompt = sprintf(static::GUS_PROMPT, $gus->name);
        }

        if ($gus->role === 'Presentaciones') {
            $gus->role = 'Producción multimedia y presentaciones';
        }

        if (empty($gus->tags)) {
            $gus->tags = ['video', 'guiones', 'clips', 'montaje', 'música', 'presentaciones', 'multimedia'];
        }

        if (empty($gus->guide)) {
            $gus->guide = [
                'Cuéntale la idea, el público y la duración: te entrega un guion por escenas con tiempos.',
                'Pídele prompts listos para generar los clips (estilo, plano y duración) y el plan de montaje con música de fondo.',
                'También ordena tus ideas en diapositivas con jerarquía clara: menos texto, más mensaje.',
                'Él escribe y planifica: no renderiza video por su cuenta. Voz y subtítulos son de Renata; las imágenes, de Paola.',
            ];
        }

        $gus->save();
    }
};
