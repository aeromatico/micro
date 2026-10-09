<?php namespace Aero\Mcp\Models;

use Aero\Mcp\Classes\McpToolRegistry;
use Model;

/**
 * Módulos del MCP activados para la plataforma. Por defecto ninguno: se van
 * activando a medida que se necesitan. Sin secretos (SettingsModel no cifra).
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_mcp_settings';

    public $settingsFields = 'fields.yaml';

    public static function enabledModules(): array
    {
        return array_values(array_filter((array) self::get('enabled_modules', []), 'is_string'));
    }

    public function getEnabledModulesOptions(): array
    {
        $options = [];

        foreach (McpToolRegistry::modules() as $module => $count) {
            $options[$module] = "{$module} ({$count} tools)";
        }

        return $options;
    }
}
