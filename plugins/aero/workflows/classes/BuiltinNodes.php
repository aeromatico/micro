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
            'logic.condition' => ['label' => 'Condición (sí / no)', 'category' => 'logic', 'handler' => [static::class, 'condition'],
                'handles' => [['id' => 'true', 'label' => 'sí'], ['id' => 'false', 'label' => 'no']],
                'fields' => [
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
            'action.reply' => ['label' => 'Responder al remitente (Hello)', 'category' => 'action', 'handler' => [static::class, 'reply'], 'fields' => [
                ['key' => 'body', 'label' => 'Mensaje', 'type' => 'textarea', 'hint' => 'Se envía a quien escribió el mensaje que disparó el workflow.'],
            ]],
            'action.reply_media' => ['label' => 'Responder con imagen o archivo (Hello)', 'category' => 'action', 'handler' => [static::class, 'replyMedia'], 'fields' => [
                ['key' => 'media_url', 'label' => 'Enlace de la imagen o archivo', 'type' => 'text', 'hint' => 'Debe empezar con https://. Ej: {{ vars.cobro.image_url }} para mandar el QR de un cobro.'],
                ['key' => 'media_type', 'label' => 'Tipo', 'type' => 'select', 'options' => [['value' => 'image', 'label' => 'Imagen'], ['value' => 'document', 'label' => 'Documento (PDF, etc.)'], ['value' => 'video', 'label' => 'Video']], 'hint' => 'Por defecto: Imagen.'],
                ['key' => 'body', 'label' => 'Texto que acompaña (opcional)', 'type' => 'textarea', 'hint' => 'Se envía a quien escribió el mensaje que disparó el workflow.'],
            ]],
            'action.notify' => ['label' => 'Notificar (Notify)', 'category' => 'action', 'handler' => [static::class, 'notify'], 'fields' => [
                ['key' => 'event', 'label' => 'Evento del catálogo', 'type' => 'text'],
                ['key' => 'context', 'label' => 'Contexto (JSON)', 'type' => 'json'],
            ]],
            'action.location' => ['label' => 'Capturar ubicación', 'category' => 'action', 'handler' => [\Aero\Workflows\Classes\Nodes\LocationCapture::class, 'handle'],
                'handles' => [['id' => 'found', 'label' => 'con ubicación'], ['id' => 'not_found', 'label' => 'sin ubicación']],
                'fields' => [
                    ['key' => 'source', 'label' => 'Texto o enlace a analizar', 'type' => 'text', 'hint' => 'Vacío = el mensaje que disparó el flujo (la ubicación de WhatsApp, coordenadas o un enlace de Maps).'],
                    ['key' => 'latitude', 'label' => 'Latitud (si ya la tienes)', 'type' => 'text'],
                    ['key' => 'longitude', 'label' => 'Longitud (si ya la tienes)', 'type' => 'text'],
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «ubicacion»: {{ vars.ubicacion.lat }}, .lng, .coords, .maps_url, .name'],
                    ['key' => 'center_lat', 'label' => 'Cobertura: latitud del centro', 'type' => 'text', 'hint' => 'Opcional. Con centro y radio se calcula in_zone y distance_km.'],
                    ['key' => 'center_lng', 'label' => 'Cobertura: longitud del centro', 'type' => 'text'],
                    ['key' => 'radius_km', 'label' => 'Cobertura: radio (km)', 'type' => 'number'],
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

    /**
     * data: body. Responde por la misma cuenta y al mismo contacto del mensaje
     * entrante (trigger.data.0). A diferencia de action.message no necesita teléfono.
     */
    public static function reply(array $data, array $ctx, ?int $tenantId): array
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            throw new \RuntimeException('Aero.Hello no está instalado.');
        }

        $body = trim((string) ($data['body'] ?? ''));
        $inbound = $ctx['trigger']['data'][0] ?? [];
        $contactId = (int) ($inbound['contact_id'] ?? 0);
        $accountId = (int) ($inbound['account_id'] ?? 0);

        if ($body === '') {
            throw new \InvalidArgumentException('action.reply necesita un texto.');
        }

        // Prueba manual (sin mensaje entrante): no hay a quién responder. No se envía nada, pero el
        // texto queda como resultado de la ejecución para poder ver qué diría el flujo.
        if (!$contactId || !$accountId) {
            return [
                'output'  => ['sent' => false, 'text' => $body, 'reason' => 'Modo prueba: no hay mensaje entrante, no se envió.'],
                'respond' => $body,
            ];
        }

        // Solo se responde a quien escribió a una cuenta de este cliente (falla cerrado).
        [$contact, $account] = \Aero\Hello\Classes\Workflows\Sender::resolve($inbound, $tenantId);

        $message = \Aero\Hello\Classes\Hello::sendToContact($contact, $body, ['tenant_id' => $tenantId, 'account_id' => $account->id]);

        return ['output' => ['message_id' => $message->id]];
    }

    /**
     * data: media_url (https), media_type (image|document|video), body (opcional).
     * Igual que `reply`: solo a quien escribió a una cuenta de este cliente.
     */
    public static function replyMedia(array $data, array $ctx, ?int $tenantId): array
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            throw new \RuntimeException('Aero.Hello no está instalado.');
        }

        $url = trim((string) ($data['media_url'] ?? ''));
        $parts = parse_url($url);

        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('action.reply_media necesita un enlace https a la imagen o archivo.');
        }

        $type = in_array($data['media_type'] ?? '', ['image', 'document', 'video'], true) ? $data['media_type'] : 'image';
        $body = trim((string) ($data['body'] ?? ''));
        $inbound = $ctx['trigger']['data'][0] ?? [];
        $contactId = (int) ($inbound['contact_id'] ?? 0);
        $accountId = (int) ($inbound['account_id'] ?? 0);

        // Prueba manual (sin mensaje entrante): no hay a quién responder; se muestra qué enviaría.
        if (!$contactId || !$accountId) {
            return [
                'output'  => ['sent' => false, 'media_url' => $url, 'media_type' => $type, 'text' => $body, 'reason' => 'Modo prueba: no hay mensaje entrante, no se envió.'],
                'respond' => trim($body . "\n" . $url),
            ];
        }

        [$contact, $account] = \Aero\Hello\Classes\Workflows\Sender::resolve($inbound, $tenantId);

        $message = \Aero\Hello\Classes\Hello::sendToContact($contact, $body, [
            'tenant_id' => $tenantId, 'account_id' => $account->id, 'media_url' => $url, 'media_type' => $type,
        ]);

        return ['output' => ['sent' => true, 'message_id' => $message->id, 'media_type' => $type]];
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
