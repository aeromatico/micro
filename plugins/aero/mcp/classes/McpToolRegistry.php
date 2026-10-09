<?php namespace Aero\Mcp\Classes;

use Aero\Mcp\Models\Settings;
use Event;

/**
 * Catálogo de tools del MCP. Dos fuentes, sin `use` duro:
 *
 *  1. Evento propio `aero.mcp.registerTools` (listener recibe ?int $tenantId):
 *       'shop_orders_list' => [
 *           'module'      => 'shop',          // agrupa para activar/dar scopes; por defecto 'general'
 *           'plugin'      => 'Aero.Shop',     // opcional: si el plan del tenant no lo incluye, no se expone
 *           'write'       => false,           // true/omitido = modifica datos (ver scopes)
 *           'description' => '…',
 *           'parameters'  => [ JSON schema ],
 *           'handler'     => [Clase::class, 'metodo'], // (array $args, int $tenantId): mixed
 *       ]
 *  2. Las AI tools de aero/chatbots (AiToolRegistry); su `category` es el módulo.
 *
 * Una tool llega al cliente solo si, en orden: su módulo está activado en
 * Ajustes → MCP, el plan del tenant incluye su plugin y la key tiene el scope.
 * `write` omitido cuenta como escritura: falla cerrado.
 */
class McpToolRegistry
{
    /** Catálogo completo sin filtros de activación (para ajustes y scopes). */
    public static function catalog(?int $tenantId = null): array
    {
        $tools = [];

        if (class_exists(\Aero\Chatbots\Classes\AiToolRegistry::class)) {
            $tools = \Aero\Chatbots\Classes\AiToolRegistry::all($tenantId);
        }

        foreach ((array) Event::fire('aero.mcp.registerTools', [$tenantId]) as $result) {
            if (is_array($result)) {
                $tools = array_merge($tools, $result);
            }
        }

        foreach ($tools as $name => &$tool) {
            $tool['module'] = (string) (($tool['module'] ?? null) ?: ($tool['category'] ?? null) ?: 'general');
            $tool['write'] = (bool) ($tool['write'] ?? true);
        }
        unset($tool);

        return $tools;
    }

    /** Tools ejecutables para el tenant: módulo activado + plan + handler válido. */
    public static function forTenant(int $tenantId): array
    {
        $enabled = Settings::enabledModules();

        if (!$enabled) {
            return [];
        }

        $plan = static::planOf($tenantId);
        $out = [];

        foreach (static::catalog($tenantId) as $name => $tool) {
            if (!in_array($tool['module'], $enabled, true)) {
                continue;
            }
            if (empty($tool['handler']) || !is_callable($tool['handler'])) {
                continue;
            }
            if ($plan && !empty($tool['plugin']) && !$plan->allowsPlugin($tool['plugin'])) {
                continue;
            }
            $out[$name] = $tool;
        }

        return $out;
    }

    /** Módulos conocidos => cantidad de tools. */
    public static function modules(): array
    {
        $modules = [];

        foreach (static::catalog() as $tool) {
            $modules[$tool['module']] = ($modules[$tool['module']] ?? 0) + 1;
        }

        ksort($modules);

        return $modules;
    }

    protected static function planOf(int $tenantId)
    {
        // Sin Sites instalado (o sin su tabla, p. ej. en tests aislados) no hay plan que aplicar.
        if (!class_exists(\Aero\Sites\Models\Tenant::class)
            || !\Schema::hasTable((new \Aero\Sites\Models\Tenant)->getTable())) {
            return null;
        }

        return \Aero\Sites\Models\Tenant::find($tenantId)?->plan;
    }
}
