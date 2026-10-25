<?php

use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Seeder;

/**
 * Completa el perfil de Bruno (integraciones y APIs): venía sin etiquetas, sin guía de uso y con el gráfico de
 * capacidades vacío (su tarjeta no mostraba radar), y su prompt lo llamaba «Bruno Claros» siendo «Bruno». Solo
 * rellena lo que sigue vacío o heredado, para respetar lo que se edite a mano. Escribe directo en la tabla.
 */
return new class extends Seeder
{
    public const TAGS = ['api', 'integraciones', 'workflows', 'webhooks', 'conexiones', 'permisos'];

    public const GUIDE = [
        'Cuéntale qué sistema quieres conectar y para qué (por ejemplo, tu tienda con una hoja de cálculo o con tu sistema de facturación).',
        'Te dice qué endpoint de la API usar, con qué permiso mínimo y te da un ejemplo de llamada para probarlo.',
        'Para conexiones automáticas consulta el catálogo real de la API y de los workflows: nunca inventa endpoints.',
        'Él explica y valida, no ejecuta la integración por ti; para armar el flujo, pídeselo a Link. No le envíes claves ni contraseñas.',
    ];

    public const CAPABILITIES = ['creatividad' => 56, 'viralidad' => 30, 'ejecucion' => 95, 'narrativa' => 58, 'estetica' => 38, 'eficiencia' => 92];

    public function run(): void
    {
        $bruno = DB::table('aero_workspaces_staff')->where('name', 'Bruno')->first(['id', 'tags', 'guide', 'capabilities', 'system_prompt']);

        if (!$bruno) {
            return;
        }

        $empty = fn ($json) => !(array) json_decode((string) $json, true);
        $set = [];

        if ($empty($bruno->tags)) {
            $set['tags'] = json_encode(static::TAGS, JSON_UNESCAPED_UNICODE);
        }
        if ($empty($bruno->guide)) {
            $set['guide'] = json_encode(static::GUIDE, JSON_UNESCAPED_UNICODE);
        }
        if ($empty($bruno->capabilities)) {
            $set['capabilities'] = json_encode(static::CAPABILITIES, JSON_UNESCAPED_UNICODE);
        }
        if (str_contains((string) $bruno->system_prompt, 'Eres Bruno Claros')) {
            $set['system_prompt'] = str_replace('Eres Bruno Claros', 'Eres Bruno', $bruno->system_prompt);
        }

        if ($set) {
            DB::table('aero_workspaces_staff')->where('id', $bruno->id)->update($set + ['updated_at' => now()]);
        }
    }
};
