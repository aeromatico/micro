<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\GymSettings;
use Aero\Gym\Models\Membership;

/**
 * Recordatorio de vencimiento por WhatsApp. Idempotente: cada membresía
 * guarda en reminders_sent qué avisos ("d3", "d1", "d0") ya salieron.
 */
class Reminders
{
    public const DEFAULT_TEMPLATE = "Hola {nombre} 👋 tu membresía {plan} {cuando} ({fecha}). Renueva por {precio} {moneda} para no perder el acceso.";

    public function run(): int
    {
        $sent = 0;
        $svc = app(MembershipService::class);

        GymSettings::where('reminders_enabled', true)->where('enabled', true)->get()->each(function (GymSettings $s) use (&$sent, $svc) {
            foreach ($s->reminderDays() as $d) {
                Membership::with(['member', 'plan'])
                    ->where('tenant_id', $s->tenant_id)->where('status', 'active')
                    ->whereDate('ends_on', today()->addDays($d)->toDateString())
                    ->get()->each(function (Membership $m) use ($d, $s, $svc, &$sent) {
                        $key = 'd' . $d;
                        $done = (array) $m->reminders_sent;
                        if (isset($done[$key]) || !$m->member) {
                            return;
                        }

                        $media = null;
                        $qr = $this->renewalQr($m, $svc);
                        if ($qr && $qr->qr_image) {
                            $media = url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image');
                        }

                        if (Notifier::whatsapp($m->member, $this->render($m, $d, $s), $media)) {
                            $done[$key] = now()->toDateTimeString();
                            $m->reminders_sent = $done;
                            $m->save();
                            $sent++;
                        }
                    });
            }
        });

        return $sent;
    }

    /** Si el tenant cobra por QR, deja lista una renovación pendiente con su QR. */
    protected function renewalQr(Membership $m, MembershipService $svc): ?\Aero\Pay\Models\QrCode
    {
        if (!$m->plan || !$m->plan->is_active) {
            return null;
        }
        $renewal = Membership::where('renewed_from_id', $m->id)->whereIn('status', ['pending', 'active'])->first()
            ?: $svc->create($m->member, $m->plan, null, false, $m);

        return $svc->issueCharge($renewal);
    }

    public function render(Membership $m, int $days, GymSettings $s): string
    {
        $cuando = match (true) {
            $days === 0 => 'vence hoy',
            $days === 1 => 'vence mañana',
            default     => "vence en {$days} días",
        };

        return strtr($s->reminder_template ?: self::DEFAULT_TEMPLATE, [
            '{nombre}' => $m->member->name,
            '{plan}'   => $m->plan?->name ?? '',
            '{cuando}' => $cuando,
            '{fecha}'  => $m->ends_on->format('d/m/Y'),
            '{precio}' => number_format((float) ($m->plan?->price ?? $m->price), 2),
            '{moneda}' => $m->currency,
        ]);
    }
}
