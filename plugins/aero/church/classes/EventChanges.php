<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Announcement;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\ChurchSettings;

/** Aviso a los inscritos cuando un evento futuro cambia de horario o lugar, o se cancela. Nunca rompe el guardado. */
class EventChanges
{
    public function handle(ChurchEvent $e, array $changed): void
    {
        try {
            if (!Messenger::available() || !$e->starts_at || $e->starts_at->isPast()) {
                return;
            }
            $settings = ChurchSettings::where('tenant_id', $e->tenant_id)->first();
            if ($settings && !$settings->notify_event_changes) {
                return;
            }
            if (!$e->registrations()->where('status', 'registered')->whereNotNull('member_id')->exists()) {
                return;
            }

            $body = $e->status === 'cancelled'
                ? "Aviso: «{$e->title}» fue cancelado. Disculpa las molestias."
                : "Aviso: «{$e->title}» cambió. Nuevo horario: " . $e->starts_at->format('d/m \a \l\a\s H:i') . ($e->location ? " en {$e->location}" : '') . '.';

            $a = Announcement::create([
                'tenant_id' => $e->tenant_id, 'title' => 'Cambio: ' . $e->title, 'body' => $body, 'kind' => 'schedule_change',
                'audience' => ['event_id' => $e->id],
            ]);
            app(AnnouncementService::class)->queue($a);
        } catch (\Throwable $ex) {
            \Log::warning('[church] aviso de cambio de evento no enviado: ' . $ex->getMessage());
        }
    }
}
