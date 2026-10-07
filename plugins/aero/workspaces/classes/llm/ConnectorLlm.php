<?php namespace Aero\Workspaces\Classes\Llm;

use Aero\Chatbots\Classes\AiToolRegistry;
use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Models\Connector;

/** Un Connector de IA (OpenAI-compatible o Anthropic) como cerebro de un agente. */
class ConnectorLlm implements LlmDriver
{
    public const ATTEMPTS = 3;

    public function __construct(protected Connector $connector, protected ?string $model = null)
    {
    }

    public function chat(array $messages, array $tools): array
    {
        $payload = ['messages' => $messages, 'model' => $this->model];

        if ($tools) {
            $payload['tools'] = AiToolRegistry::toProviderFormat($this->connector->type, $tools);
            $payload['tool_choice'] = 'auto';
        }

        $client = app(ConnectorClient::class);
        $response = null;

        for ($attempt = 1; $attempt <= static::ATTEMPTS; $attempt++) {
            $response = $client->send($this->connector, $payload);

            if ($response->successful) {
                break;
            }

            if ($attempt < static::ATTEMPTS) {
                usleep(700_000);
            }
        }

        if (!$response || !$response->successful) {
            \Log::warning('aero.workspaces: el modelo de IA no respondió', ['connector_id' => $this->connector->id, 'status' => $response?->statusCode, 'error' => $response?->error]);

            throw new \RuntimeException('El modelo de IA no respondió (' . ($response?->error ?: 'HTTP ' . $response?->statusCode) . ').');
        }

        return EngineAccess::reply($this->connector, $response);
    }

    public function toolMessages(array $results): array
    {
        return EngineAccess::toolMessages($this->connector, $results);
    }
}
