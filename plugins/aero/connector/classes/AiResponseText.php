<?php namespace Aero\Connector\Classes;

use Aero\Connector\Models\Connector;

/**
 * Extrae el texto de respuesta de un ConnectorResponse sin importar el
 * formato del proveedor (OpenAI-compatible vs Anthropic nativo). Aero.Chatbots
 * tiene una copia equivalente de esta lógica (ChatbotEngine::extractAiReplyText)
 * escrita antes de que este helper existiera — se deja así para no arriesgar
 * ese flujo ya probado; los consumidores nuevos (ej. Aero.Sites) usan este.
 */
class AiResponseText
{
    public static function extract(Connector $connector, ConnectorResponse $response): ?string
    {
        $body = is_array($response->body) ? $response->body : json_decode((string) $response->rawBody, true);
        if (!is_array($body)) {
            return null;
        }

        $text = $connector->type === 'ai_anthropic'
            ? static::firstAnthropicTextBlock($body['content'] ?? [])
            : ($body['choices'][0]['message']['content'] ?? null);

        return $text ? trim($text) : null;
    }

    protected static function firstAnthropicTextBlock(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                return $block['text'] ?? null;
            }
        }

        return null;
    }
}
