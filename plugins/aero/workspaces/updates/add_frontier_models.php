<?php

use Aero\Workspaces\Models\Settings;
use October\Rain\Database\Updates\Migration;

/**
 * Amplía el catálogo de modelos especializados (Ajustes → Workspaces) con los
 * modelos frontera más usados y los modelos abiertos de mayor nivel.
 *
 * Idempotente y aditiva: solo agrega los códigos que no existen, nunca pisa
 * ni reordena lo que el superadmin ya haya definido.
 *
 * Los IDs usan el formato proveedor/modelo del gateway. Los de Anthropic y
 * OpenAI vienen activos; los demás quedan INACTIVOS hasta confirmar que el
 * gateway/conector en uso los expone con ese nombre (un ID mal escrito falla
 * en el primer turno). Se activan desde Ajustes sin tocar código.
 */
return new class extends Migration
{
    private const MODELS = [
        // Frontera (cerrados)
        ['code' => 'claude_opus',   'label' => 'Frontera · Claude Opus 5.5',   'kind' => 'text', 'model' => 'anthropic/claude-opus-5-5',   'on' => true],
        ['code' => 'claude_sonnet', 'label' => 'Frontera · Claude Sonnet 5.5', 'kind' => 'text', 'model' => 'anthropic/claude-sonnet-5-5', 'on' => true],
        ['code' => 'claude_fable',  'label' => 'Frontera · Claude Fable 5.1',  'kind' => 'text', 'model' => 'anthropic/claude-fable-5-1',  'on' => true],
        ['code' => 'claude_haiku',  'label' => 'Rápido · Claude Haiku 5.5',    'kind' => 'text', 'model' => 'anthropic/claude-haiku-5-5',  'on' => true],
        ['code' => 'gpt_astra',     'label' => 'Frontera · GPT-6 Astra',       'kind' => 'text', 'model' => 'openai/gpt-6-astra',          'on' => true],
        ['code' => 'gemini_argon',  'label' => 'Frontera · Gemini 4 Argon',    'kind' => 'text', 'model' => 'google-ai-studio/gemini-4-argon', 'on' => false],
        ['code' => 'grok_47',       'label' => 'Frontera · Grok 4.7',          'kind' => 'text', 'model' => 'grok/grok-4.7',               'on' => false],
        // Abiertos de mayor nivel
        ['code' => 'kimi_k3',       'label' => 'Abierto · Kimi K3 (código)',   'kind' => 'code', 'model' => 'moonshot/kimi-k3',            'on' => false],
        ['code' => 'glm_53',        'label' => 'Abierto · GLM-5.3 (código)',   'kind' => 'code', 'model' => 'zai/glm-5.3',                 'on' => false],
    ];

    public function up(): void
    {
        $current = array_values((array) Settings::get('models', []));
        $known = array_column($current, 'code');

        foreach (self::MODELS as $m) {
            if (in_array($m['code'], $known, true)) {
                continue;
            }

            $current[] = [
                'code'         => $m['code'],
                'label'        => $m['label'],
                'kind'         => $m['kind'],
                'model'        => $m['model'],
                'connector_id' => '',
                'is_active'    => $m['on'],
            ];
        }

        Settings::set('models', $current);
    }

    public function down(): void
    {
        // Sin reversa destructiva: el catálogo es del superadmin.
    }
};
