<?php namespace Aero\Workflows\Classes;

use Aero\Workflows\Models\Workflow;

/**
 * Puente OPCIONAL con el Super Chatbot IA (Aero.Chatbots). Doble interruptor:
 *   1. el bot debe tener marcada la categoría «workflows»; y
 *   2. el workflow debe tener «Ofrecer como herramienta» activo.
 * Solo se ofrecen los workflows del tenant del bot.
 */
class AiTools
{
    public const CATEGORY = 'workflows';

    public static function tools(?int $tenantId): array
    {
        if (!$tenantId) {
            return [];
        }

        $tools = [];

        $workflows = Workflow::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where('expose_as_tool', true)
            ->get();

        foreach ($workflows as $workflow) {
            $workflowId = $workflow->id;

            $tools[$workflow->tool_name] = [
                'description' => $workflow->tool_description ?: ($workflow->description ?: $workflow->name),
                'category'    => static::CATEGORY,
                'parameters'  => $workflow->jsonField('tool_schema') ?: null,
                'handler'     => fn (array $arguments, int $tenantId) => static::run($workflowId, $arguments, $tenantId),
            ];
        }

        return $tools;
    }

    protected static function run(int $workflowId, array $arguments, int $tenantId): array
    {
        // El tenant que manda es el del bot: si el workflow no coincide, no existe.
        $workflow = Workflow::where('id', $workflowId)->where('tenant_id', $tenantId)->where('is_active', true)->where('status', 'published')->first();

        if (!$workflow) {
            return ['error' => 'Workflow no disponible.'];
        }

        $run = WorkflowRunner::start($workflow, $arguments, 'ai_tool', sync: true);

        if (!$run) {
            return ['error' => 'Límite de ejecuciones alcanzado, intenta más tarde.'];
        }

        return $run->status === 'ok'
            ? ['ok' => true, 'result' => $run->decoded('result')]
            : ['ok' => false, 'error' => $run->error ?: 'El workflow no terminó correctamente.'];
    }
}
