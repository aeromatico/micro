<?php

use October\Rain\Database\Updates\Migration;
use Aero\Connector\Models\Connector;

/**
 * Vidriera de modelos de IA vía OpenRouter (un solo proveedor, un solo API
 * key, muchos modelos) — pensada para "presumir variedad" en el selector de
 * generación de Aero.Sites sin tener que dar de alta una cuenta por
 * proveedor. Se crean deshabilitados (`is_enabled = false`) y sin
 * `credit_cost` (el modelo de precios por conector todavía no está
 * estandarizado): activar cada uno a mano una vez cargada la API key de
 * OpenRouter en "Secret"/"api_key", y confirmar que el slug en `ai_model`
 * sigue vigente en https://openrouter.ai/models (los proveedores renombran/
 * retiran modelos con el tiempo — verificado contra ese catálogo el
 * 2026-09-08, puede haber cambiado).
 */
return new class extends Migration
{
    protected array $models = [
        ['name' => 'DeepSeek V4 Pro (OpenRouter)',   'model' => 'deepseek/deepseek-v4-pro'],
        ['name' => 'Qwen 3 Max (OpenRouter)',        'model' => 'qwen/qwen3-max'],
        ['name' => 'Kimi K2 (OpenRouter)',           'model' => 'moonshotai/kimi-k2-0905'],
        ['name' => 'GLM-4.6 (OpenRouter)',           'model' => 'z-ai/glm-4.6'],
        ['name' => 'MiniMax M2 (OpenRouter)',        'model' => 'minimax/minimax-m2'],
        ['name' => 'GPT-5.5 Pro (OpenRouter)',       'model' => 'openai/gpt-5.5-pro'],
        ['name' => 'Gemini 3.1 Pro (OpenRouter)',    'model' => 'google/gemini-3.1-pro-preview'],
        ['name' => 'Grok 4.6 (OpenRouter)',          'model' => 'x-ai/grok-4.6'],
        ['name' => 'Mistral Large (OpenRouter)',     'model' => 'mistralai/mistral-large-2512'],
        ['name' => 'Llama 4 Maverick (OpenRouter)',  'model' => 'meta-llama/llama-4-maverick'],
    ];

    public function up(): void
    {
        foreach ($this->models as $entry) {
            if (Connector::where('name', $entry['name'])->exists()) {
                continue;
            }

            $connector = new Connector();
            $connector->name = $entry['name'];
            $connector->provider_hint = 'openrouter';
            $connector->config = ['model' => $entry['model']];
            $connector->is_enabled = false;
            $connector->save();
        }
    }

    public function down(): void
    {
        Connector::whereIn('name', array_column($this->models, 'name'))->delete();
    }
};
