<?php namespace Aero\Workspaces\Classes\Llm;

/**
 * Un modelo principal con respaldo. Si el principal no responde (ConnectorLlm ya
 * reintentó varias veces), el turno sigue con el respaldo y se queda en él hasta
 * terminar: el historial del turno ya está en el formato de ese proveedor.
 */
class FallbackLlm implements LlmDriver
{
    protected bool $onFallback = false;

    public function __construct(protected LlmDriver $primary, protected LlmDriver $fallback)
    {
    }

    public function chat(array $messages, array $tools): array
    {
        if (!$this->onFallback) {
            try {
                return $this->primary->chat($messages, $tools);
            }
            catch (\RuntimeException $e) {
                \Log::warning('aero.workspaces: el modelo principal falló, se usa el respaldo', ['error' => $e->getMessage()]);
                $this->onFallback = true;
            }
        }

        return $this->fallback->chat($messages, $tools);
    }

    public function toolMessages(array $results): array
    {
        return ($this->onFallback ? $this->fallback : $this->primary)->toolMessages($results);
    }

    /** ¿El principal falló y respondió el respaldo? */
    public function usedFallback(): bool
    {
        return $this->onFallback;
    }
}
