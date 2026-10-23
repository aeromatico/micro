<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\TaskRate;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Paola (slug `paola`) pasa a trabajar de verdad con imágenes: el skill «Estudio de imágenes»
 * (image_context / image_search / image_generate), su tarifa por imagen generada (4 monedas de
 * bronce) y su perfil. Refresca también el skill de la orquestadora para que le asigne las
 * imágenes. El skill se refresca siempre desde `skills/`; la tarifa, el tipo de crédito, el
 * prompt y el rol solo se tocan si siguen sin definir o en borrador, así lo que el superadmin
 * edite se conserva.
 */
return new class extends Seeder
{
    public const TOOLS = ['image_context', 'image_search', 'image_generate'];

    public const FEE = 4;

    public const PROMPT = <<<'TXT'
Eres %s, diseñadora de imágenes del equipo. Consigues fotos, portadas, miniaturas e ilustraciones: una foto real gratis o una imagen creada con IA, que se cobra en créditos por cada imagen.

Sigue al pie de la letra el skill «Estudio de imágenes» y léelo al empezar.

Reglas que nunca rompes:
1. Empiezas con `image_context` y le dices a la persona el precio por imagen generada antes de generar nada.
2. Generas solo con su «sí» claro al precio; cada imagen (también las repeticiones) se cobra.
3. Recomiendas la foto real (gratis) cuando alcanza; la IA cuando hace falta algo específico.
4. Distingues siempre foto real de imagen de IA, y entregas los enlaces tal cual los devolvió la herramienta.
5. Nunca dices que algo se generó o se cobró si la herramienta no lo confirmó.

Habla en español cercano, breve y claro.
TXT;

    protected function body(string $slug): ?array
    {
        $raw = @file_get_contents(dirname(__DIR__) . '/skills/' . $slug . '/SKILL.md');

        if ($raw === false) {
            return null;
        }

        $description = null;

        if (preg_match('/^---\R(.*?)\R---\R/s', $raw, $m)) {
            if (preg_match('/^description:\s*(.+)$/m', $m[1], $d)) {
                $description = trim($d[1]);
            }

            $raw = substr($raw, strlen($m[0]));
        }

        return [trim($raw), $description];
    }

    public function run(): void
    {
        if (!Schema::hasTable('aero_workspaces_skills') || !($staff = Staff::where('slug', 'paola')->first())) {
            return;
        }

        if ($img = $this->body('image-studio')) {
            $skill = Skill::firstOrNew(['tenant_id' => null, 'slug' => 'image-studio']);
            $skill->forceFill([
                'kind' => 'official', 'name' => 'Estudio de imágenes', 'description' => mb_substr((string) $img[1], 0, 1000),
                'body' => $img[0], 'tools' => static::TOOLS,
            ])->save();
            $staff->skills()->syncWithoutDetaching([$skill->id]);
        }

        // La orquestadora aprende a repartir las imágenes (solo el texto; nombre y herramientas no se tocan).
        if (($orch = $this->body('team-orchestration')) && ($katy = Skill::whereNull('tenant_id')->where('slug', 'team-orchestration')->first())) {
            $katy->forceFill(['body' => $orch[0]])->save();
        }

        if (!TaskRate::where('staff_id', $staff->id)->where('task_type', 'imagen')->exists()) {
            TaskRate::create(['staff_id' => $staff->id, 'task_type' => 'imagen', 'fee' => static::FEE]);
        }

        $prompt = trim((string) $staff->system_prompt);

        if ($prompt === '' || str_contains($prompt, '[BORRADOR')) {
            $staff->system_prompt = sprintf(static::PROMPT, $staff->name);
            $staff->role = 'Diseñadora de imágenes y miniaturas';
            $staff->bio = 'Consigue la imagen que necesitas: una foto real gratis o una imagen creada con IA (se cobra por imagen, con aviso del precio antes de generar). Portadas, miniaturas, productos y fondos.';
            $staff->tags = ['imágenes', 'fotos', 'miniaturas', 'portadas', 'ilustración', 'IA'];
            $staff->guide = [
                'Cuéntale para qué quieres la imagen y en qué formato (cuadrada, horizontal o vertical).',
                'Te propone una foto real (gratis) o una imagen creada con IA y te dice el precio por imagen antes de generar.',
                'Solo genera con tu «sí». Cada imagen generada se cobra, también las repeticiones; si falla, se te devuelve.',
            ];
        }

        if (trim((string) $staff->credit_type) === '') {
            $staff->credit_type = 'bronce';
        }

        $staff->fee_mode = 'credits';
        // Sin esto no aparece en el Mercado ni se puede contratar.
        $staff->status = Staff::ONLINE;
        $staff->is_active = true;
        $staff->save();
    }
};
