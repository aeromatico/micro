<?php namespace Aero\School\Classes;

use Aero\School\Models\Charge;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;

/**
 * Recordatorios de cobranza por WhatsApp. Idempotente: cada cuota guarda en
 * reminders_sent qué avisos ("d3", "d0", "o":[fechas]) ya salieron. Agrupa por
 * alumno: un solo mensaje por apoderado con todo lo que corresponde avisar.
 */
class Collections
{
    public const DEFAULT_UPCOMING = "Hola {apoderado} 👋 le recordamos de {colegio}: {detalle}\nTotal a pagar: {total} {moneda}. Puede pagar en secretaría o por QR. ¡Gracias!";

    public const DEFAULT_OVERDUE = "Estimado(a) {apoderado}, de {colegio}: {estudiante} tiene cuotas vencidas:\n{detalle}\nTotal vencido: {total} {moneda}. Le pedimos regularizar su pago. Si ya pagó, por favor ignore este aviso.";

    public function run(): int
    {
        $sent = 0;
        Settings::where('enabled', true)->where('reminders_enabled', true)->get()->each(function (Settings $s) use (&$sent) {
            $sent += $this->runFor($s);
        });

        return $sent;
    }

    public function runFor(Settings $s): int
    {
        $sent = 0;
        $days = $s->reminderDays();

        $byStudent = [];
        $open = Charge::where('tenant_id', $s->tenant_id)->open()->whereNotNull('due_on')->get();
        foreach ($open as $c) {
            $diff = today()->diffInDays($c->due_on->copy()->startOfDay(), false); // >0 faltan días, <0 vencida
            if ($diff >= 0 && in_array((int) $diff, $days, true)) {
                $key = 'd' . (int) $diff;
                if (!isset(($c->reminders_sent ?: [])[$key])) {
                    $byStudent[$c->student_id]['upcoming'][] = [$c, $key];
                }
            } elseif ($diff < 0) {
                $log = ($c->reminders_sent ?: [])['o'] ?? [];
                $last = $log ? \Carbon\Carbon::parse(end($log)) : null;
                if (count($log) < $s->overdue_max && (!$last || $last->diffInDays(now(), true) >= $s->overdue_every_days)) {
                    $byStudent[$c->student_id]['overdue'][] = [$c, 'o'];
                }
            }
        }

        foreach ($byStudent as $studentId => $groups) {
            $student = Student::with('guardians')->find($studentId);
            if (!$student || $student->status !== 'active') {
                continue;
            }
            foreach (['overdue' => 'overdue_template', 'upcoming' => 'reminder_template'] as $type => $tplField) {
                $items = $groups[$type] ?? [];
                if (!$items) {
                    continue;
                }
                if ($this->notify($student, $s, $type, array_column($items, 0), $s->{$tplField})) {
                    $sent++;
                    foreach ($items as [$c, $key]) {
                        $log = $c->reminders_sent ?: [];
                        if ($key === 'o') {
                            $log['o'] = array_merge($log['o'] ?? [], [now()->toDateTimeString()]);
                        } else {
                            $log[$key] = now()->toDateTimeString();
                        }
                        $c->reminders_sent = $log;
                        $c->save();
                    }
                }
            }
        }

        return $sent;
    }

    /** @param array<int,Charge> $charges */
    public function notify(Student $student, Settings $s, string $type, array $charges, ?string $template): bool
    {
        $currency = $s->currency ?: 'BOB';
        $detail = collect($charges)->map(fn (Charge $c) => '• ' . $c->concept . ' — ' . number_format($c->due, 2) . ' ' . $currency
            . ' (vence ' . $c->due_on->format('d/m/Y') . ')')->implode("\n");
        $total = collect($charges)->sum(fn (Charge $c) => $c->due);

        $ok = false;
        foreach ($student->financialGuardians() as $g) {
            $body = Notifier::render($template, $type === 'overdue' ? self::DEFAULT_OVERDUE : self::DEFAULT_UPCOMING, [
                'apoderado' => $g->name, 'estudiante' => $student->full_name, 'colegio' => Notifier::schoolName($student->tenant_id),
                'detalle' => $detail, 'total' => number_format($total, 2), 'moneda' => $currency,
            ]);
            $ok = Notifier::whatsapp($student->tenant_id, $g->phone, $body, $g->name) || $ok;
        }

        return $ok;
    }
}
