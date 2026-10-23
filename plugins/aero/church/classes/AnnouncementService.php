<?php namespace Aero\Church\Classes;

use Aero\Church\Jobs\SendAnnouncementJob;
use Aero\Church\Models\Announcement;
use Aero\Church\Models\AnnouncementRecipient;
use Illuminate\Support\Facades\DB;

/** Vista previa, envío y registro de comunicados. */
class AnnouncementService
{
    public function __construct(protected Recipients $recipients)
    {
    }

    /** Cuántos recibirían el comunicado y cuántos quedan fuera por falta de consentimiento o teléfono. */
    public function preview(Announcement $a): array
    {
        $r = $this->recipients->forAnnouncement($a);

        return ['recipients' => $r['recipients']->count(), 'skipped' => $r['skipped']];
    }

    /** Pasa el comunicado a «enviando» una sola vez (bloqueo) y lo manda a la cola. */
    public function queue(Announcement $a): void
    {
        if (!Messenger::available() && !app()->environment('testing')) {
            throw new ChurchException('WhatsApp no está disponible: conecta una cuenta en Hello.');
        }

        $claimed = DB::transaction(function () use ($a) {
            $row = Announcement::whereKey($a->id)->lockForUpdate()->first();
            if (!$row || $row->status !== 'draft') {
                return false;
            }
            $row->status = 'sending';
            $row->save();

            return true;
        });

        if (!$claimed) {
            throw new ChurchException('Este comunicado ya se envió o se está enviando.');
        }

        SendAnnouncementJob::dispatch($a->id);
    }

    /** Envía de verdad. Idempotente por destinatario: si el job se repite, no reenvía a quien ya salió. */
    public function deliver(Announcement $a): void
    {
        $r = $this->recipients->forAnnouncement($a);
        $done = AnnouncementRecipient::where('announcement_id', $a->id)->where('status', 'sent')->pluck('phone')->all();

        $sent = count($done);
        $failed = 0;
        foreach ($r['recipients'] as $to) {
            if (in_array($to['phone'], $done, true)) {
                continue;
            }
            [$ok, $err] = Messenger::whatsapp($a->tenant_id, $to['phone'], $this->render($a->body, $to['name']), $to['name']);
            AnnouncementRecipient::create([
                'tenant_id' => $a->tenant_id, 'announcement_id' => $a->id, 'member_id' => $to['member_id'],
                'phone' => $to['phone'], 'status' => $ok ? 'sent' : 'failed', 'error' => $err,
            ]);
            $ok ? $sent++ : $failed++;
        }

        $a->forceFill([
            'status' => 'sent', 'sent_at' => now(), 'recipients_count' => $r['recipients']->count(),
            'sent_count' => $sent, 'failed_count' => $failed, 'skipped_count' => $r['skipped'],
        ])->saveQuietly();

        \Event::fire('aero.church.announcementSent', [$a]);
        Audit::record('sent', 'announcement', (int) $a->id, "Comunicado #{$a->id} enviado a {$r['recipients']->count()} personas", (int) $a->tenant_id);
    }

    public function render(string $body, string $name): string
    {
        return strtr($body, ['{nombre}' => $name]);
    }
}
