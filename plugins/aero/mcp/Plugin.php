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

    public function register(): void
    {
        $this->registerConsoleCommand('mcp.audit', Console\Audit::class);
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

            foreach (Classes\McpToolRegistry::catalog() as $name => $tool) {
                $scopes["mcp.tool.{$name}"] = 'Tool MCP: ' . ($tool['description'] ?? $name);
            }

            foreach (array_keys(Classes\McpToolRegistry::modules()) as $module) {
                $scopes["mcp.module.{$module}"] = "Módulo MCP {$module}: todas sus tools de lectura";
                $scopes["mcp.module.{$module}.write"] = "Módulo MCP {$module}: tools que modifican datos";
            }

            return ['mcp' => ['label' => 'MCP — Herramientas', 'scopes' => $scopes]];
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.mcp.superadmin' => [
                'tab'   => 'MCP',
                'label' => 'Activar módulos del servidor MCP',
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'MCP',
                'description' => 'Módulos de plugins expuestos a clientes MCP.',
                'category'    => 'Sistema',
                'icon'        => 'icon-plug',
                'class'       => Models\Settings::class,
                'order'       => 516,
                'permissions' => ['aero.mcp.superadmin'],
            ],
        ];
    }
}
