<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\Llm\LlmDriver;

/** Modelo de mentira: devuelve una vuelta tras otra y guarda lo que recibió. */
class FakeLlm implements LlmDriver
{
    public array $seen = [];
    protected int $i = 0;

    public function __construct(protected array $turns)
    {
    }

    public function chat(array $messages, array $tools): array
    {
        $this->seen[] = ['messages' => $messages, 'tools' => array_keys($tools)];
        $turn = $this->turns[$this->i++] ?? ['text' => 'fin'];

        return ['text' => $turn['text'] ?? null, 'calls' => $turn['calls'] ?? [], 'assistant' => ['role' => 'assistant', 'content' => null]];
    }

    public function toolMessages(array $results): array
    {
        return array_map(fn ($r) => ['role' => 'tool', 'tool_call_id' => $r['id'], 'content' => json_encode($r['result'], JSON_UNESCAPED_UNICODE)], $results);
    }
}
