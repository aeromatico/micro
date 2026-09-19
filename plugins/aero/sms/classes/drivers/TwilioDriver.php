<?php namespace Aero\Sms\Classes\Drivers;

use Aero\Sms\Models\Message;
use Aero\Sms\Models\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Twilio por REST directo (sin SDK): un solo endpoint, y así no añadimos una
 * dependencia de composer para una llamada.
 */
class TwilioDriver implements SmsDriverInterface
{
    public function code(): string
    {
        return 'twilio';
    }

    public function send(Message $message): DriverResult
    {
        $sid = Settings::get('twilio_account_sid');
        $token = Settings::get('twilio_auth_token');

        if (!$sid || !$token) {
            throw new SmsDriverException('Faltan las credenciales de Twilio en Configuración.');
        }

        $payload = [
            'To'             => $message->to,
            'Body'           => $message->body,
            'StatusCallback' => url('api/v1/sms/webhooks/twilio'),
        ];

        if ($service = Settings::get('twilio_messaging_service_sid')) {
            $payload['MessagingServiceSid'] = $service;
        }
        elseif ($from = Settings::get('twilio_from')) {
            $payload['From'] = $from;
        }
        else {
            throw new SmsDriverException('Configura un número remitente o un Messaging Service en Twilio.');
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(20)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload);
        }
        catch (ConnectionException $e) {
            throw new SmsDriverException('Sin conexión con Twilio: ' . $e->getMessage(), null, true);
        }

        if ($response->failed()) {
            $code = (string) $response->json('code');
            // 429 y 5xx se reintentan; 4xx (número inválido, sin fondos) no.
            throw new SmsDriverException(
                $response->json('message') ?: 'Twilio respondió ' . $response->status(),
                $code ?: null,
                $response->status() === 429 || $response->serverError()
            );
        }

        return new DriverResult($response->json('sid'), 'sent');
    }

    /**
     * Twilio firma el webhook: HMAC-SHA1 del URL completo más los parámetros
     * POST ordenados y concatenados, con el Auth Token como clave.
     */
    public static function validSignature(string $url, array $params, ?string $signature): bool
    {
        $token = Settings::get('twilio_auth_token');

        if (!$token || !$signature) {
            return false;
        }

        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $data, $token, true)), $signature);
    }
}
