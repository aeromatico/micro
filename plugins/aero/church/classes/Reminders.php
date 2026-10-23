<?php namespace Aero\Church\Classes;

use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\EventRegistration;

/**
 * Recordatorio de eventos por WhatsApp (vía Aero.Hello, integración blanda). Solo a miembros
 * con consentimiento de mensajes y teléfono; idempotente por `reminder_sent_at`.
 */
class Reminders
{
    public static bool $muted = false;

    public function run(): int
    {
        if (self::$muted || !class_exists(\Aero\Hello\Classes\Hello::class)) {
            return 0;
        }

        $sent = 0;
        ChurchEvent::where('status', 'scheduled')->whereNotNull('reminder_hours')
            ->where('starts_at', '>', now())
            ->whereRaw('starts_at <= DATE_ADD(?, INTERVAL reminder_hours HOUR)', [now()->toDateTimeString()])
            ->get()->each(function (ChurchEvent $event) use (&$sent) {
                $event->registrations()->where('status', 'registered')->whereNull('reminder_sent_at')
                    ->whereNotNull('member_id')->with('member')->get()
                    ->each(function (EventRegistration $reg) use ($event, &$sent) {
                        if ($this->send($reg, $event)) {
                            $reg->reminder_sent_at = now();
                            $reg->save();
                            $sent++;
                        }
                    });
            });

        return $sent;
    }

    public function render(ChurchEvent $event, string $name): string
    {
        $when = $event->starts_at->translatedFormat('l d/m \a \l\a\s H:i');

        return "Hola {$name} 🙏 te recordamos «{$event->title}»: {$when}" . ($event->location ? " en {$event->location}" : '') . '.';
    }

    protected function send(EventRegistration $reg, ChurchEvent $event): bool
    {
        $m = $reg->member;
        $phone = preg_replace('/\D+/', '', (string) $m?->phone);
        if (!$m || !$m->consent_messages || !$phone) {
            return false;
        }

        try {
            \Aero\Hello\Classes\Hello::send($phone, $this->render($event, $m->first_name), [
                'platform' => 'whatsapp', 'tenant_id' => $event->tenant_id, 'name' => $m->full_name,
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('[church] recordatorio no enviado: ' . $e->getMessage());

            return false;
        }
    }
}
