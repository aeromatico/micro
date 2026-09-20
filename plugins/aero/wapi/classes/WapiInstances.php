<?php namespace Aero\Wapi\Classes;

use Aero\Wapi\Models\Settings;

/**
 * Operaciones de instancias/webhooks usadas solo desde el backend de
 * October (controllers/Instances.php) para el flujo de conexión por QR.
 * WapiChannelDriver no depende de esta clase — solo envía/recibe mensajes.
 */
class WapiInstances
{
    public function __construct(protected WapiClient $client = new WapiClient())
    {
    }

    /**
     * Interpreta lo que mandan las pantallas de conexión: method=code exige el
     * número de WhatsApp a vincular (dígitos con código de país); cualquier otro
     * valor es el flujo normal por QR y devuelve null.
     */
    public static function pairingPhoneFromInput(?string $method, ?string $rawPhone): ?string
    {
        if ($method !== 'code') {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $rawPhone);

        if (!preg_match('/^[0-9]{8,15}$/', $digits)) {
            throw new \ApplicationException('Escribe el número de WhatsApp que vas a vincular, con código de país y solo dígitos (ej. 59170000000).');
        }

        return $digits;
    }

    public function create(string $name): array
    {
        return $this->client->post('/instances', ['name' => $name, 'provider' => 'baileys']);
    }

    /**
     * Con $pairingPhone (dígitos con código de país) wapi vincula con un código
     * de 8 caracteres en vez de QR - alternativa para cuentas cuyo QR se cae
     * con LOGOUT justo después de escanear. El modo queda fijo al conectar.
     */
    public function connect(string $instanceId, ?string $pairingPhone = null): void
    {
        $this->client->post("/instances/{$instanceId}/connect", $pairingPhone ? ['phoneNumber' => $pairingPhone] : []);
    }

    public function status(string $instanceId): array
    {
        return $this->client->get("/instances/{$instanceId}/status");
    }

    /**
     * Texto crudo para renderizar como QR en el cliente (qrcodejs), no una
     * imagen — ver el docblock de WapiChannelDriver o el propio código de
     * wapi (apps/qr/app.js) para la confirmación. Null mientras no hay QR
     * disponible (instancia recién creada, o ya conectada).
     */
    public function qr(string $instanceId): ?string
    {
        return $this->client->getOrNull("/instances/{$instanceId}/qr")['qr'] ?? null;
    }

    /**
     * Código de 8 caracteres para teclear en el celular. Null mientras wapi
     * no lo tiene listo (recién conectada) o si la instancia usa QR.
     */
    public function pairingCode(string $instanceId): ?string
    {
        return $this->client->getOrNull("/instances/{$instanceId}/pairing-code")['code'] ?? null;
    }

    public function delete(string $instanceId): void
    {
        $this->client->delete("/instances/{$instanceId}");
    }

    /**
     * Registra el webhook de mensajes entrantes para la instancia recién
     * conectada. Genera el webhook secret una sola vez si todavía no existe
     * uno guardado — todas las instancias comparten el mismo secreto.
     */
    public const WEBHOOK_EVENTS = [
        // message_ack = entregado/leído; disconnected/authenticated = estado de la cuenta;
        // message_create = lo que el dueño escribe desde otro dispositivo (fromMe): sin
        // este evento esos mensajes nunca llegan a Hello y el chat no los muestra.
        'message', 'message_create', 'message_ack', 'disconnected', 'authenticated',
    ];

    /**
     * Idempotente: si la instancia ya tiene un webhook hacia nuestra URL lo
     * actualiza (nuevos eventos, mismo secreto) en vez de crear otro — POST
     * /webhooks siempre inserta, y un duplicado entregaría cada evento dos veces.
     */
    public function registerMessageWebhook(string $instanceId): void
    {
        $secret = Settings::getWebhookSecret();

        if (!$secret) {
            $secret = bin2hex(random_bytes(24));
            Settings::set(['webhook_secret' => $secret]);
        }

        $url = Settings::webhookUrl($instanceId);
        $existing = collect($this->client->get('/webhooks')['data'] ?? [])
            ->first(fn ($w) => ($w['instanceId'] ?? null) === $instanceId && ($w['url'] ?? null) === $url);

        if ($existing) {
            $this->client->put('/webhooks/' . $existing['id'], ['events' => self::WEBHOOK_EVENTS, 'secret' => $secret, 'isActive' => true]);
            return;
        }

        $this->client->post('/webhooks', [
            'url'        => $url,
            'instanceId' => $instanceId,
            'secret'     => $secret,
            'events'     => self::WEBHOOK_EVENTS,
        ]);
    }
}
