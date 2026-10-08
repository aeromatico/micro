<?php

use Aero\Workspaces\Models\Settings;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Modelos especializados: el agente elige uno del catálogo de Ajustes (`model_code`)
 * y se carga el catálogo inicial (código máx./normal, imagen, audio, video).
 * Idempotente: nunca pisa lo que el superadmin ya haya definido.
 *
 * Los IDs son los del gateway de Cloudflare (proveedor/modelo) y se editan en
 * Ajustes → Workspaces.
 */
return new class extends Migration
{
    private const MODELS = [
        ['code' => 'code_max',    'label' => 'Código · máximo (DeepSeek V4 Pro)',    'kind' => 'code',  'model' => 'deepseek/deepseek-v4-pro'],
        ['code' => 'code_normal', 'label' => 'Código · normal (DeepSeek V4.1 Flash)', 'kind' => 'code',  'model' => 'deepseek/deepseek-flash'],
        ['code' => 'image',       'label' => 'Imagen (GPT Image 1)',                  'kind' => 'image', 'model' => 'openai/gpt-image-1'],
        ['code' => 'audio',       'label' => 'Audio · voz (GPT-4o mini TTS)',         'kind' => 'audio', 'model' => 'openai/gpt-4o-mini-tts'],
        ['code' => 'video',       'label' => 'Video (Sora 2)',                        'kind' => 'video', 'model' => 'openai/sora-2'],
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('aero_workspaces_staff', 'model_code')) {
            Schema::table('aero_workspaces_staff', function (Blueprint $table) {
                $table->string('model_code', 40)->nullable()->after('connector_id');
            });
        }

        if (!Settings::get('models')) {
            Settings::set('models', array_map(fn ($m) => $m + ['connector_id' => '', 'is_active' => true], self::MODELS));
        }

        // El conector de los agentes: el Cloudflare AI Gateway, si existe y no hay uno elegido.
        if (!Settings::get('agent_connector_id') && class_exists(\Aero\Connector\Models\Connector::class) && Schema::hasTable('aero_connector_connectors')) {
            $id = \Aero\Connector\Models\Connector::where('provider_hint', 'cloudflare_ai_gateway')->orderBy('id')->value('id');

            if ($id) {
                Settings::set('agent_connector_id', $id);
            }
        }
    }

    public function down(): void
    {
        // Sin reversa destructiva: la columna y los ajustes son aditivos.
    }
};
