<?php namespace Aero\Mcp\Models;

use Aero\Api\Models\ApiKey;
use Aero\Mcp\Classes\McpToolRegistry;

/**
 * Una API key dedicada al MCP: misma tabla y mismo mecanismo que ApiKey, pero
 * su formulario solo concede módulos MCP (lectura / escritura) y obliga a un
 * tenant y a una fecha de expiración.
 */
class McpKey extends ApiKey
{
    public $rules = [
        'name'                  => 'required',
        'rate_limit_per_minute' => 'required|integer|min:1|max:10000',
        'owner_id'              => 'required|integer',
        'expires_at'            => 'required|date',
    ];

    /** Scopes de módulo (los que gestiona esta pantalla). */
    public static function isModuleScope(string $scope): bool
    {
        return str_starts_with($scope, 'mcp.module.');
    }

    public function getScopesOptions(): array
    {
        $enabled = Settings::enabledModules();
        $options = [];

        foreach (McpToolRegistry::modules() as $module => $count) {
            $note = in_array($module, $enabled, true) ? '' : ' — desactivado en Ajustes';
            $options["mcp.module.{$module}"] = "{$module}: lectura ({$count} tools){$note}";
            $options["mcp.module.{$module}.write"] = "{$module}: escritura";
        }

        return $options;
    }

    public function getOwnerIdOptions(): array
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return [];
        }

        return \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
    }

    public function scopeMcpOnly($query)
    {
        return $query->where('scopes', 'like', '%"mcp.use"%');
    }
}
