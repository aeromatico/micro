<?php namespace Aero\Mcp\Classes;

use Aero\Api\Models\ApiKey;
use Aero\Api\Models\ApiKeyLog;
use Throwable;

/**
 * Servidor MCP (Model Context Protocol) sobre JSON-RPC 2.0, transporte HTTP
 * sin streaming. Es solo un adaptador: las tools viven en AiToolRegistry
 * (aero/chatbots) y este servidor no duplica ninguna lógica.
 *
 * Reglas de seguridad, en orden:
 *  1. La key ya pasó por el middleware `aero.api:mcp.use`.
 *  2. El tenant sale del dueño de la key, nunca de los argumentos.
 *  3. Una tool solo se lista o ejecuta si su módulo está activado en Ajustes,
 *     el plan del tenant incluye su plugin y la key tiene el scope
 *     `mcp.tool.<nombre>` o el de su módulo. Por defecto, ninguna.
 *  4. Una tool que no está permitida responde igual que una inexistente,
 *     para no revelar qué existe.
 */
class McpServer
{
    public const PROTOCOL_VERSION = '2025-06-18';
    public const SERVER_NAME = 'aero-mcp';
    public const SERVER_VERSION = '1.2.0';

    public function __construct(protected ApiKey $key, protected ?int $tenantId, protected ?string $ip = null)
    {
    }

    /**
     * Procesa un mensaje JSON-RPC (o un lote). Devuelve null cuando no hay
     * respuesta que enviar (notificaciones).
     */
    public function handle(array $payload): ?array
    {
        if (array_is_list($payload)) {
            $responses = array_values(array_filter(array_map([$this, 'dispatch'], $payload)));

            return $responses ?: null;
        }

        return $this->dispatch($payload);
    }

    protected function dispatch($message): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0' || !is_string($message['method'] ?? null)) {
            return $this->error(null, -32600, 'Petición JSON-RPC inválida.');
        }

        $id = $message['id'] ?? null;
        $isNotification = !array_key_exists('id', $message);
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            $result = match ($message['method']) {
                'initialize'                => $this->initialize(),
                'ping'                      => [],
                'tools/list'                => $this->listTools(),
                'tools/call'                => $this->callTool($params),
                'notifications/initialized' => null,
                default                     => throw new McpException('Método no soportado: ' . $message['method'], -32601),
            };
        } catch (McpException $e) {
            return $isNotification ? null : $this->error($id, $e->getCode(), $e->getMessage());
        } catch (Throwable $e) {
            return $isNotification ? null : $this->error($id, -32603, 'Error interno del servidor.');
        }

        if ($isNotification) {
            return null;
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result ?? new \stdClass()];
    }

    protected function initialize(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => ['tools' => ['listChanged' => false]],
            'serverInfo'      => ['name' => self::SERVER_NAME, 'version' => self::SERVER_VERSION],
        ];
    }

    /** Solo lista las tools que esta key puede ejecutar. */
    protected function listTools(): array
    {
        $tools = [];

        foreach ($this->allowedTools() as $name => $tool) {
            $tools[] = [
                'name'        => $name,
                'description' => (string) ($tool['description'] ?? $name),
                'inputSchema' => $this->schemaFor($tool),
            ];
        }

        return ['tools' => $tools];
    }

    protected function callTool(array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (!is_string($name) || $name === '') {
            throw new McpException('Falta el nombre de la tool.', -32602);
        }

        if ($this->tenantId === null) {
            throw new McpException('Esta API key no pertenece a un tenant.', -32002);
        }

        $tools = $this->allowedTools();

        if (!isset($tools[$name])) {
            throw new McpException("Tool no encontrada: {$name}", -32602);
        }

        // El tenant_id siempre es el de la key; un valor que mande el cliente se descarta.
        unset($arguments['tenant_id']);

        try {
            $output = call_user_func($tools[$name]['handler'], $arguments, $this->tenantId);
            $status = 200;
            $isError = false;
            $text = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        } catch (ToolError $e) {
            // Mensaje pensado para el cliente (validación, no encontrado…).
            $status = 422;
            $isError = true;
            $text = $e->getMessage();
        } catch (Throwable $e) {
            // Se devuelve como error de la tool, no como error de protocolo, y sin detalles internos.
            $status = 500;
            $isError = true;
            $text = 'La tool falló al ejecutarse.';
        }

        // La auditoría no debe tumbar la llamada: si falla el registro, la tool ya se ejecutó.
        try {
            ApiKeyLog::record($this->key, 'MCP', 'tools/call', $status, "mcp.tool.{$name}", $this->ip);
        } catch (Throwable $e) {
        }

        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => $isError,
        ];
    }

    /**
     * Tools del tenant de la key, filtradas por su scope. Clave = nombre.
     */
    protected function allowedTools(): array
    {
        if ($this->tenantId === null) {
            return [];
        }

        $allowed = [];

        foreach (McpToolRegistry::forTenant($this->tenantId) as $name => $tool) {
            if ($this->scopeAllows($name, $tool)) {
                $allowed[$name] = $tool;
            }
        }

        return $allowed;
    }

    /**
     * Scope de la tool, o de su módulo: `mcp.module.<m>` cubre las de lectura,
     * `mcp.module.<m>.write` las de escritura.
     */
    protected function scopeAllows(string $name, array $tool): bool
    {
        if ($this->key->hasScope("mcp.tool.{$name}")) {
            return true;
        }

        return $this->key->hasScope("mcp.module.{$tool['module']}" . ($tool['write'] ? '.write' : ''));
    }

    protected function schemaFor(array $tool): array
    {
        $schema = is_array($tool['parameters'] ?? null) ? $tool['parameters'] : [];

        // JSON Schema exige `properties` como objeto: un array vacío de PHP se serializa como lista.
        if (empty($schema['properties'])) {
            $schema['properties'] = new \stdClass();
        }

        return array_merge(['type' => 'object'], $schema);
    }

    protected function error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
