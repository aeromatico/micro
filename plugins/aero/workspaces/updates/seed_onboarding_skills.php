<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Skills de puesta en marcha por rubro para Violeta (gimnasios, inmobiliarias, tiendas y CRM),
 * hermanos de «Puesta en marcha de consultorios». Reutilizan las herramientas sheets_*.
 * Los skills se refrescan siempre desde `skills/<slug>/`; el prompt solo recibe la línea nueva
 * si aún no la tiene.
 */
return new class extends Seeder
{
    public const SLUGS = [
        'gym-onboarding'        => 'Puesta en marcha de gimnasios',
        'realestate-onboarding' => 'Puesta en marcha de inmobiliarias',
        'shop-onboarding'       => 'Puesta en marcha de tiendas',
        'crm-onboarding'        => 'Puesta en marcha del CRM',
    ];

    public const TOOLS = [
        'sheets_context', 'sheets_inspect', 'sheets_read', 'sheets_write', 'sheets_append', 'sheets_create',
        'sheets_suggest_mapping', 'sheets_mapping_save', 'sheets_preview', 'sheets_run', 'sheets_undo', 'sheets_history',
    ];

    public const LINE = "\n\nCuando la persona hable de montar un gimnasio, una inmobiliaria, una tienda o su CRM, usa el skill de «Puesta en marcha» que corresponda: pregunta primero el tipo de negocio y propón llenar los datos juntos, importar con Google Sheets o cargar datos de ejemplo ficticios.";

    public function run(): void
    {
        if (!Schema::hasTable('aero_workspaces_skills') || !($staff = Staff::where('slug', 'violeta')->first())) {
            return;
        }

        $ids = [];

        foreach (static::SLUGS as $slug => $name) {
            $raw = @file_get_contents(dirname(__DIR__) . '/skills/' . $slug . '/SKILL.md');

            if ($raw === false) {
                continue;
            }

            $description = $name;

            if (preg_match('/^---\R(.*?)\R---\R/s', $raw, $m)) {
                if (preg_match('/^description:\s*(.+)$/m', $m[1], $d)) {
                    $description = trim($d[1]);
                }

                $raw = substr($raw, strlen($m[0]));
            }

            $skill = Skill::firstOrNew(['tenant_id' => null, 'slug' => $slug]);
            $skill->forceFill([
                'kind' => 'official', 'name' => $name, 'description' => mb_substr($description, 0, 1000),
                'body' => trim($raw), 'tools' => static::TOOLS,
            ])->save();
            $ids[] = $skill->id;
        }

        if (!str_contains((string) $staff->system_prompt, 'gimnasio, una inmobiliaria')) {
            $staff->system_prompt = rtrim((string) $staff->system_prompt) . static::LINE;
            $staff->save();
        }

        $staff->skills()->syncWithoutDetaching($ids);
    }
};
