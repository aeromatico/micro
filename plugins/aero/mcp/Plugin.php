<?php namespace Aero\Mcp;

use Event;
use System\Classes\PluginBase;

/**
 * Servidor MCP global de la plataforma. Es un adaptador: expone las tools que
 * registran los plugins en Aero\Chatbots\Classes\AiToolRegistry, con la
 * autenticación y el permiso de aero/api (API keys y scopes).
 *
 * Integración por eventos, sin `use` duro: si aero/chatbots no está instalado,
 * el endpoint responde sin tools.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'MCP',
            'description' => 'Servidor MCP global: expone las tools de los plugins a clientes externos (con API keys y scopes).',
            'author'      => 'Aero',
            'icon'        => 'icon-plug',
            'homepage'    => 'https://github.com/aeromatico/api-market',
        ];
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        // `mcp.use` da acceso al endpoint; cada tool tiene su scope `mcp.tool.<nombre>`
        // y ninguna key lo tiene por defecto.
        Event::listen('aero.api.registerScopes', function () {
            $scopes = ['mcp.use' => 'Usar el endpoint MCP (/api/v1/mcp)'];

            if (class_exists(\Aero\Chatbots\Classes\AiToolRegistry::class)) {
                foreach (\Aero\Chatbots\Classes\AiToolRegistry::all() as $name => $tool) {
                    $scopes["mcp.tool.{$name}"] = 'Tool MCP: ' . ($tool['description'] ?? $name);
                }
            }

            return ['mcp' => ['label' => 'MCP — Herramientas', 'scopes' => $scopes]];
        });
    }
}
