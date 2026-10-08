<?php namespace Aero\Workspaces\Classes\Llm;

use Aero\Chatbots\Classes\ChatbotEngine;
use Aero\Connector\Models\Connector;
use Aero\Connector\Classes\ConnectorResponse;

/**
 * Acceso público a los helpers de parseo del ChatbotEngine (protegidos), para no
 * duplicar cómo se leen las respuestas de cada proveedor. Solo se carga si
 * Aero.Chatbots está instalado.
 */
class EngineAccess extends ChatbotEngine
{
    public static function reply(Connector $connector, ConnectorResponse $response): array
    {
        return [
            'text'      => static::extractAiReplyText($connector, $response),
            'calls'     => static::extractToolCalls($connector, $response),
            'assistant' => static::buildAssistantToolCallMessage($connector, $response),
        ];
    }

    public static function toolMessages(Connector $connector, array $results): array
    {
        return static::buildToolResultMessages($connector, $results);
    }
}
