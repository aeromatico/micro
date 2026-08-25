<?php namespace Aero\Sites\Classes\Notifications;

use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\NotificationChannel;
use Log;

/**
 * Envía la notificación de contacto por WhatsApp a través de Aero.Hello
 * (Zernio). Dependencia blanda: Aero.Sites no requiere Aero.Hello, así que
 * si el plugin no está instalado el canal simplemente no envía.
 */
class WhatsappNotificationDriver implements NotificationDriverInterface
{
    public function send(ContactSubmission $submission, NotificationChannel $channel): bool
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            Log::warning('Aero\\Sites: canal WhatsApp inactivo — el plugin Aero.Hello no está instalado.');
            return false;
        }

        $config = $channel->config;
        $to = preg_replace('/[^0-9]/', '', (string) ($config['whatsapp_to'] ?? ''));

        if (!$to) {
            return false;
        }

        $account = $this->resolveAccount($channel, $config);

        if (!$account) {
            Log::warning('Aero\\Sites: canal WhatsApp sin cuenta de Hello asociada', ['channel_id' => $channel->id]);
            return false;
        }

        $text = sprintf(
            "Nuevo mensaje de contacto\n\nNombre: %s\nEmail: %s\nTeléfono: %s\n\n%s",
            $submission->name,
            $submission->email,
            $submission->phone ?? 'N/A',
            mb_substr($submission->message, 0, 1000),
        );

        try {
            // Vía la fachada y no el driver directo: así el envío queda como
            // Message en la bandeja de Hello, con reintentos y estado, en vez
            // de ser una llamada suelta a la API que no deja rastro.
            \Aero\Hello\Classes\Hello::send($to, $text, ['account_id' => $account->id]);

            return true;
        }
        catch (\Throwable $ex) {
            Log::warning('Aero\\Sites: envío WhatsApp vía Hello falló', [
                'channel_id' => $channel->id,
                'error'      => $ex->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cuenta explícita del canal, o la primera cuenta WhatsApp habilitada del
     * tenant (vía profile) si el canal no fija ninguna.
     */
    protected function resolveAccount(NotificationChannel $channel, array $config)
    {
        $accountClass = \Aero\Hello\Models\Account::class;

        if (!empty($config['hello_account_id'])) {
            return $accountClass::find($config['hello_account_id']);
        }

        return $accountClass::enabled()
            ->ofPlatform('whatsapp')
            ->whereHas('profile', fn ($query) => $query->where('tenant_id', $channel->tenant_id))
            ->first();
    }
}
