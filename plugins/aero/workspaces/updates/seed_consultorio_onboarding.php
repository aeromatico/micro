<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Le suma a Violeta (slug `violeta`) el skill «Puesta en marcha de consultorios»: pregunta
 * el tipo de consultorio y propone llenar los datos juntos, importar con Aero.Sheets o cargar
 * datos de ejemplo ficticios. Reutiliza las herramientas sheets_*. El skill se refresca siempre
 * desde `skills/consultorio-onboarding/`; el prompt solo recibe la línea nueva si aún no la tiene.
 */
return new class extends Seeder
{
    public const TOOLS = [
        'sheets_context', 'sheets_inspect', 'sheets_read', 'sheets_write', 'sheets_append', 'sheets_create',
        'sheets_suggest_mapping', 'sheets_mapping_save', 'sheets_preview', 'sheets_run', 'sheets_undo', 'sheets_history',
    ];

    public const LINE = "\n\nCuando la persona hable de un consultorio (médico, veterinaria, psicología, nutrición, odontología, enfermería…) usa el skill «Puesta en marcha de consultorios»: pregunta primero el tipo de consultorio y propón llenar los datos juntos, importar con Google Sheets o cargar datos de ejemplo ficticios.";

    public function run(): void
    {
        if (!Schema::hasTable('aero_workspaces_skills')) {
            return;
        }

        $staff = Staff::where('slug', 'violeta')->first();
        $raw = @file_get_contents(dirname(__DIR__) . '/skills/consultorio-onboarding/SKILL.md');

        if (!$staff || $raw === false) {
            return;
        }

        $description = 'Úsala cuando la persona tenga o quiera montar un consultorio.';

        if (preg_match('/^---\R(.*?)\R---\R/s', $raw, $m)) {
            if (preg_match('/^description:\s*(.+)$/m', $m[1], $d)) {
                $description = trim($d[1]);
            }

            $raw = substr($raw, strlen($m[0]));
        }

        $skill = Skill::firstOrNew(['tenant_id' => null, 'slug' => 'consultorio-onboarding']);
        $skill->forceFill([
            'kind' => 'official', 'name' => 'Puesta en marcha de consultorios', 'description' => mb_substr($description, 0, 1000),
            'body' => trim($raw), 'tools' => static::TOOLS,
        ])->save();

        if (!str_contains((string) $staff->system_prompt, 'Puesta en marcha de consultorios')) {
            $staff->system_prompt = rtrim((string) $staff->system_prompt) . static::LINE;
            $staff->save();
        }

        $staff->skills()->syncWithoutDetaching([$skill->id]);
    }
};
