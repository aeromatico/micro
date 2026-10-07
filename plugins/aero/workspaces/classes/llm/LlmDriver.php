<?php namespace Aero\Workspaces\Classes\Llm;

/**
 * El modelo de IA detrás de un agente. Una interfaz mínima para poder probar el
 * bucle del agente sin llamar a ningún proveedor.
 */
interface LlmDriver
{
    /**
     * Una vuelta al modelo.
     *
     * @param array $messages  conversación en formato OpenAI (system/user/assistant/tool)
     * @param array $tools     herramientas del AiToolRegistry (nombre => definición)
     * @return array{text: ?string, calls: array<int, array{id: ?string, name: ?string, arguments: array}>, assistant: array}
     * @throws \RuntimeException si el modelo no respondió
     */
    public function chat(array $messages, array $tools): array;

    /** Mensajes con los resultados de las herramientas, en el formato del proveedor. @param array<int, array{id: ?string, result: mixed}> $results */
    public function toolMessages(array $results): array;
}
