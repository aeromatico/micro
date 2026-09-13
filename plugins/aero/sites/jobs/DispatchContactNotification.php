<?php namespace Aero\Sites\Jobs;

use Aero\Sites\Models\ContactSubmission;
use Queue;

/**
 * Dispara 'sites.contact.submitted' en Aero.Notify. Reemplaza al viejo
 * NotificationDispatcher (aero/sites/classes/notifications, dado de baja) —
 * las credenciales por tenant que antes vivían en NotificationChannel ahora
 * son Aero\Notify\Models\Channel, consultadas por Notify::deliverOne() para
 * cualquier canal con Rule, no solo el contacto.
 *
 * Aero.Sites no requiere Aero.Notify (es al revés: Notify requiere Sites),
 * así que se guarda con class_exists() — mismo patrón que
 * TenantInvite::notify().
 */
class DispatchContactNotification
{
    public ContactSubmission $submission;

    public function __construct(ContactSubmission $submission)
    {
        $this->submission = $submission;
    }

    public function fire($job, $data): void
    {
        $submission = ContactSubmission::find($data['submission_id']);

        if (!$submission) {
            $job->delete();
            return;
        }

        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            $submission->markAsFailed();
            $job->delete();
            return;
        }

        $deliveries = \Aero\Notify\Classes\Notify::fire('sites.contact.submitted', [
            'name'        => $submission->name,
            'email'       => $submission->email,
            'phone'       => $submission->phone,
            'message'     => $submission->message,
            'page'        => $submission->metadata['referer'] ?? null,
            'tenant_name' => $submission->tenant->name ?? null,
        ], [
            'tenant_id' => $submission->tenant_id,
        ]);

        $this->markSubmission($submission, $deliveries);

        $job->delete();
    }

    /**
     * Un delivery 'skipped' (sin Channel configurado, sin plantilla) no es
     * un fallo del envío: solo failed/sent cuentan para el estado. Sin
     * ningún intento real (todo skipped), se considera enviado — no hay
     * nada roto que reportar, es la config actual del tenant.
     */
    protected function markSubmission(ContactSubmission $submission, array $deliveries): void
    {
        $sent = 0;
        $failed = 0;

        foreach ($deliveries as $delivery) {
            if ($delivery->status === 'sent') {
                $sent++;
            } elseif ($delivery->status === 'failed') {
                $failed++;
            }
        }

        if ($failed === 0) {
            $submission->markAsSent();
        } elseif ($sent > 0) {
            $submission->markAsPartial();
        } else {
            $submission->markAsFailed();
        }
    }

    public static function dispatch(ContactSubmission $submission): void
    {
        Queue::push(static::class, [
            'submission_id' => $submission->id,
        ]);
    }
}
