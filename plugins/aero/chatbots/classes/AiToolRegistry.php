<?php namespace Aero\Chatbots\Classes;

use Event;
use Aero\Chatbots\Models\Bot;

/**
 * Catálogo de "AI tools" (function calling) disponibles para el modo Súper
 * IA. Cualquier plugin declara las suyas por evento, mismo patrón que
 * `Aero\Connector\Classes\TypeRegistry`:
 *
 *     Event::listen('aero.chatbots.registerAiTools', function () {
 *         return [
 *             'mi_tool' => [
 *                 'description' => 'Qué hace, en lenguaje simple para el LLM.',
 *                 'category'    => 'site', // 'site' | 'shop' | null (null = siempre disponible)
 *                 'parameters'  => [ ... JSON schema estilo OpenAI ... ],
 *                 'handler'     => [MiClase::class, 'miMetodo'], // (array $arguments, int $tenantId): mixed
 *             ],
 *         ];
 *     });
 *
 * `category` es lo que Bot::ai_tool_categories habilita/deshabilita por bot
 * (ver Bot::getAiToolCategoryOptions y ChatbotEngine::resolveToolsForBot).
 * El handler SIEMPRE recibe el tenant_id resuelto por ChatbotEngine (el del
 * bot) como segundo argumento — nunca hay que confiar en un tenant_id que
 * venga dentro de $arguments, aunque el LLM lo mande.
 */
class AiToolRegistry
{
    protected static ?array $tools = null;

    public static function all(): array
    {
        if (static::$tools !== null) {
            return static::$tools;
        }

        $tools = [];

        foreach ((array) Event::fire('aero.chatbots.registerAiTools') as $result) {
            if (is_array($result)) {
                $tools = array_merge($tools, $result);
            }
        }

        return static::$tools = $tools;
    }

    public static function find(string $name): ?array
    {
        return static::all()[$name] ?? null;
    }

    /**
     * Tools habilitadas para un bot: las de categoría `null` siempre entran,
     * el resto solo si su categoría está en `Bot::ai_tool_categories`.
     */
    public static function forBot(Bot $bot): array
    {
        $enabledCategories = (array) ($bot->ai_tool_categories ?: []);

        return array_filter(static::all(), function (array $tool) use ($enabledCategories) {
            $category = $tool['category'] ?? null;

            return $category === null || in_array($category, $enabledCategories, true);
        });
    }

    /**
     * Todas las categorías declaradas por las tools registradas, para el
     * checkboxlist del formulario del Bot (`getAiToolCategoryOptions`).
     */
    public static function categories(): array
    {
        $categories = [];

        foreach (static::all() as $tool) {
            if (!empty($tool['category'])) {
                $categories[$tool['category']] = true;
            }
        }

        return array_keys($categories);
    }

    /**
     * Traduce el catálogo genérico de tools al formato "wire" que espera
     * cada tipo de connector. Centralizado acá para que los drivers de
     * Aero.Connector no necesiten saber nada de "tools genéricas" — solo
     * reenvían lo que ya viene armado.
     */
    public static function toProviderFormat(string $connectorType, array $tools): array
    {
        $payload = [];

        foreach ($tools as $name => $tool) {
            $parameters = static::normalizeParameters($tool['parameters'] ?? null);

            if ($connectorType === 'ai_anthropic') {
                $payload[] = [
                    'name'         => $name,
                    'description'  => $tool['description'] ?? '',
                    'input_schema' => $parameters,
                ];
            }
            else {
                $payload[] = [
                    'type'     => 'function',
                    'function' => [
                        'name'        => $name,
                        'description' => $tool['description'] ?? '',
                        'parameters'  => $parameters,
                    ],
                ];
            }
        }

        return $payload;
    }

    /**
     * `json_encode([])` produce `[]`, no `{}` — un JSON Schema con
     * `properties` vacío como array (en vez de objeto) es inválido y varios
     * proveedores lo rechazan. Normaliza `properties` (y `parameters` si
     * viene vacío del todo) a un objeto real.
     */
    protected static function normalizeParameters(?array $parameters): array|object
    {
        $parameters = $parameters ?: ['type' => 'object', 'properties' => new \stdClass()];

        if (empty($parameters['properties'])) {
            $parameters['properties'] = new \stdClass();
        }

        return $parameters;
    }

    public static function flush(): void
    {
        static::$tools = null;
    }
}
