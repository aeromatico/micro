<?php namespace Aero\Notify\Traits;

use Aero\Notify\Classes\Notify;

/**
 * Para cualquier modelo que quiera ofrecer "avisame por WhatsApp/correo
 * cuando esto pase" a alguien que NO es un usuario del sistema (un cliente
 * que solo dejó su número/correo en un form). Requiere 3 columnas propias en
 * la tabla consumidora — no se pueden compartir entre plugins — creadas con
 * una migración de 3 líneas:
 *
 *     $table->string('alert_prefix', 8)->nullable()->default('+591');
 *     $table->string('alert_phone', 30)->nullable();
 *     $table->string('alert_email')->nullable();
 *
 * En el form (fields.yaml o create_fields.yaml), un solo campo reusable:
 *
 *     _alert_recipient:
 *         type: partial
 *         path: $/aero/notify/partials/_alert_recipient_fields.htm
 *         span: full
 *
 * Y en el controller, tras persistir el registro:
 *
 *     $model->fireAlert('mi.plugin.evento', ['amount' => ..., ...]);
 *
 * El evento debe existir en el catálogo de Aero.Notify con
 * `default_audiences: ['adhoc']` y tener plantilla para los canales que uses
 * (whatsapp/email) — ver EventCatalog y la migración que siembra event+rules+
 * templates de un evento nuevo.
 */
trait HasAdhocAlert
{
    public function getAlertPrefixOptions(): array
    {
        return [
            '+591' => '🇧🇴 +591',
            '+54'  => '🇦🇷 +54',
            '+56'  => '🇨🇱 +56',
            '+51'  => '🇵🇪 +51',
            '+55'  => '🇧🇷 +55',
            '+1'   => '🇺🇸 +1',
        ];
    }

    /**
     * Número completo solo dígitos (sin "+"), como Aero.Hello guarda el
     * `external_id` de una identidad de WhatsApp — mandar con "+" no
     * matchea un contacto/conversación ya existente y arranca uno nuevo sin
     * sesión activa (Meta lo trata como mensaje en frío).
     */
    public function getAlertWhatsappNumberAttribute(): ?string
    {
        if (!$this->alert_phone) {
            return null;
        }

        $prefixDigits = preg_replace('/\D+/', '', $this->alert_prefix ?: '591');
        $phoneDigits  = preg_replace('/\D+/', '', $this->alert_phone);

        return $phoneDigits === '' ? null : $prefixDigits . $phoneDigits;
    }

    public function hasAlertRecipient(): bool
    {
        return (bool) ($this->alert_phone || $this->alert_email);
    }

    /**
     * Dispara el evento hacia el destinatario suelto cargado en este
     * registro. $context son las variables de la plantilla (más
     * media_url/media_type o attachment_binary/attachment_filename/
     * attachment_mime si el evento manda un adjunto — ver
     * WhatsAppDriver/EmailDriver). No lanza si no hay a quién avisar.
     */
    public function fireAlert(string $eventCode, array $context = []): array
    {
        if (!$this->hasAlertRecipient()) {
            return [];
        }

        $recipient = [
            'name'  => $context['to_name'] ?? null,
            'email' => $this->alert_email,
            'phone' => $this->alert_whatsapp_number,
        ];

        return Notify::fire($eventCode, $context, [
            'tenant_id' => $this->tenant_id ?? 0,
            'adhoc'     => [$recipient],
        ]);
    }
}
