<?php namespace Aero\Workflows\Classes;

use Aero\Workflows\Models\Run;

/**
 * Nodos del MVP. Cada `data` llega ya con las plantillas resueltas.
 */
class BuiltinNodes
{
    /**
     * `fields` alimenta el inspector del editor visual (type: text | textarea |
     * number | select | json | connector). Cualquier plugin puede declarar los
     * suyos igual en `aero.workflows.registerNodes`.
     */
    public static function definitions(): array
    {
        return [
            'trigger.manual'  => ['label' => 'Manual / prueba', 'category' => 'trigger', 'handler' => [static::class, 'trigger'], 'fields' => []],
            'trigger.event'   => ['label' => 'Evento de la plataforma', 'category' => 'trigger', 'handler' => [static::class, 'trigger'], 'fields' => []],
            'trigger.message' => ['label' => 'Mensaje entrante', 'category' => 'trigger', 'handler' => [static::class, 'trigger'], 'fields' => []],
            'trigger.webhook' => ['label' => 'Webhook entrante', 'category' => 'trigger', 'handler' => [static::class, 'trigger'], 'fields' => []],
            'logic.condition' => ['label' => 'Condición (sí / no)', 'category' => 'logic', 'handler' => [static::class, 'condition'], 'fields' => [
                ['key' => 'left', 'label' => 'Valor', 'type' => 'text', 'hint' => 'Ej: {{ trigger.plan }}'],
                ['key' => 'op', 'label' => 'Operador', 'type' => 'select', 'options' => [
                    ['value' => 'eq', 'label' => 'es igual a'], ['value' => 'neq', 'label' => 'es distinto de'],
                    ['value' => 'contains', 'label' => 'contiene'], ['value' => 'gt', 'label' => 'es mayor que'],
                    ['value' => 'lt', 'label' => 'es menor que'], ['value' => 'empty', 'label' => 'está vacío'],
                    ['value' => 'notempty', 'label' => 'no está vacío'],
                ]],
                ['key' => 'right', 'label' => 'Comparar con', 'type' => 'text'],
            ]],
            'logic.set' => ['label' => 'Guardar variable', 'category' => 'logic', 'handler' => [static::class, 'setVar'], 'fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'hint' => 'Luego: {{ vars.nombre }}'],
                ['key' => 'value', 'label' => 'Valor', 'type' => 'text'],
            ]],
            'logic.delay' => ['label' => 'Esperar', 'category' => 'logic', 'handler' => [static::class, 'delay'], 'fields' => [
                ['key' => 'seconds', 'label' => 'Segundos (máx. 86400)', 'type' => 'number'],
            ]],
            'action.http' => ['label' => 'Llamar URL / Connector', 'category' => 'action', 'handler' => [static::class, 'http'], 'fields' => [
                ['key' => 'connector_id', 'label' => 'Connector (recomendado)', 'type' => 'connector', 'hint' => 'Las credenciales viven cifradas en el Connector.'],
                ['key' => 'url', 'label' => 'URL https (si no hay Connector)', 'type' => 'text'],
                ['key' => 'method', 'label' => 'Método', 'type' => 'select', 'options' => [
                    ['value' => 'GET', 'label' => 'GET'], ['value' => 'POST', 'label' => 'POST'],
                    ['value' => 'PUT', 'label' => 'PUT'], ['value' => 'PATCH', 'label' => 'PATCH'], ['value' => 'DELETE', 'label' => 'DELETE'],
                ]],
                ['key' => 'payload', 'label' => 'Datos (JSON)', 'type' => 'json'],
            ]],
            'action.message' => ['label' => 'Enviar mensaje (Hello)', 'category' => 'action', 'handler' => [static::class, 'message'], 'fields' => [
                ['key' => 'to', 'label' => 'Teléfono destino', 'type' => 'text', 'hint' => 'Ej: {{ trigger.data.0.contact.phone }}'],
                ['key' => 'body', 'label' => 'Mensaje', 'type' => 'textarea'],
                ['key' => 'account_id', 'label' => 'ID de cuenta (opcional)', 'type' => 'number'],
            ]],
            'action.notify' => ['label' => 'Notificar (Notify)', 'category' => 'action', 'handler' => [static::class, 'notify'], 'fields' => [
                ['key' => 'event', 'label' => 'Evento del catálogo', 'type' => 'text'],
                ['key' => 'context', 'label' => 'Contexto (JSON)', 'type' => 'json'],
            ]],
            'action.respond' => ['label' => 'Responder (valor de retorno)', 'category' => 'action', 'handler' => [static::class, 'respond'], 'fields' => [
                ['key' => 'value', 'label' => 'Valor', 'type' => 'textarea', 'hint' => 'Es lo que recibe quien llamó (p. ej. la IA).'],
            ]],
        ];
    }

    public static function trigger(array $data, array $ctx): array
    {
        return ['output' => $ctx['trigger'] ?? []];
    }

    /** data: left, op (eq|neq|contains|gt|lt|empty|notempty), right. Handles: true / false. */
    public static function condition(array $data): array
    {
        $left = $data['left'] ?? null;
        $right = $data['right'] ?? null;

        $result = match ($data['op'] ?? 'eq') {
            'neq'      => (string) $left !== (string) $right,
            'contains' => is_scalar($left) && is_scalar($right) && $right !== '' && stripos((string) $left, (string) $right) !== false,
            'gt'       => is_numeric($left) && is_numeric($right) && $left > $right,
            'lt'       => is_numeric($left) && is_numeric($right) && $left < $right,
            'empty'    => $left === null || $left === '' || $left === [],
            'notempty' => !($left === null || $left === '' || $left === []),
            default    => (string) $left === (string) $right,
        };

        return ['output' => $result, 'handle' => $result ? 'true' : 'false'];
    }

    /** data: name, value. El runner guarda `output` en vars.<name>. */
    public static function setVar(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('El nodo «Guardar variable» necesita un nombre.');
        }

        return ['output' => $data['value'] ?? null, 'var' => $name];
    }

    /** data: seconds (máx. 1 día). Corta el run y lo retoma con un job con delay. */
    public static function delay(array $data): array
    {
        $seconds = max(1, min(86400, (int) ($data['seconds'] ?? 60)));

        return ['output' => ['seconds' => $seconds], 'wait' => $seconds];
    }

    /**
     * data: connector_id (recomendado: las credenciales viven cifradas en el
     * Connector) o url directa (https, pasa por SafeUrl). method, payload.
     */
    public static function http(array $data, array $ctx, ?int $tenantId): array
    {
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        if (!empty($data['connector_id'])) {
            if (!class_exists(\Aero\Connector\Classes\ConnectorClient::class)) {
                throw new \RuntimeException('Aero.Connector no está instalado.');
            }

            // El Connector debe ser del mismo tenant que el workflow.
            $connector = \Aero\Connector\Models\Connector::where('id', $data['connector_id'])
                ->where('owner_type', \Aero\Sites\Models\Tenant::class)
                ->where('owner_id', $tenantId ?: 0)
                ->where('is_enabled', true)
                ->first();

            if (!$connector) {
                throw new \RuntimeException('Connector no encontrado o no pertenece a este tenant.');
            }

            $response = (new \Aero\Connector\Classes\ConnectorClient())->send($connector, $payload);

            if (!$response->successful) {
                throw new \RuntimeException('El Connector falló: ' . ($response->error ?: 'HTTP ' . $response->statusCode));
            }

            return ['output' => ['status' => $response->statusCode, 'body' => $response->body]];
        }

        $result = SafeUrl::request((string) ($data['method'] ?? 'GET'), (string) ($data['url'] ?? ''), $payload);

        if (!$result['ok']) {
            throw new \RuntimeException('La llamada HTTP falló: ' . $result['error']);
        }

        return ['output' => ['status' => $result['status'], 'body' => $result['body']]];
    }

    /** data: to (teléfono), body, account_id (opcional). Usa Aero.Hello. */
    public static function message(array $data, array $ctx, ?int $tenantId): array
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            throw new \RuntimeException('Aero.Hello no está instalado.');
        }

        $to = trim((string) ($data['to'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($to === '' || $body === '') {
            throw new \InvalidArgumentException('El nodo de mensaje necesita destinatario y texto.');
        }

        $options = ['tenant_id' => $tenantId];

        if (!empty($data['account_id'])) {
            // La cuenta debe ser del tenant del workflow.
            $account = \Aero\Hello\Models\Account::where('id', $data['account_id'])->where('tenant_id', $tenantId ?: 0)->first();

            if (!$account) {
                throw new \RuntimeException('La cuenta indicada no pertenece a este tenant.');
            }

            $options['account_id'] = $account->id;
        }

        $message = \Aero\Hello\Classes\Hello::send($to, $body, $options);

        return ['output' => ['message_id' => $message->id]];
    }

    /** data: event (código del catálogo de Notify), context. */
    public static function notify(array $data): array
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            throw new \RuntimeException('Aero.Notify no está instalado.');
        }

        $result = \Aero\Notify\Classes\Notify::fire((string) ($data['event'] ?? ''), (array) ($data['context'] ?? []));

        return ['output' => ['sent' => count($result)]];
    }

    /** data: value. Es lo que recibe quien llamó al workflow (p. ej. la IA). */
    public static function respond(array $data): array
    {
        return ['output' => $data['value'] ?? null, 'respond' => $data['value'] ?? null];
    }
}
