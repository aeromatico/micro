<?php namespace Aero\School\Classes;

use Aero\School\Models\Charge;
use Aero\School\Models\FeePlan;
use Aero\School\Models\Guardian;
use Aero\School\Models\Payment;
use Aero\School\Models\PaymentItem;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;
use Carbon\Carbon;
use Event;
use Illuminate\Support\Facades\DB;

/** Cuotas, pagos, recibos y cobro por QR. */
class Billing
{
    public const MONTHS = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** Genera (o completa) las cuotas del concepto para todos sus alumnos. Idempotente. Devuelve cuántas creó. */
    public function generate(FeePlan $plan, ?Student $only = null): int
    {
        $year = $this->yearNumber($plan);
        $students = $only ? collect([$only]) : $plan->targetStudents()->get();
        $created = 0;

        foreach ($students as $student) {
            foreach ($this->installmentsFor($plan, $year) as $i => [$period, $due, $concept]) {
                $exists = Charge::where('student_id', $student->id)->where('fee_plan_id', $plan->id)->where('period', $period)->exists();
                if ($exists) {
                    continue;
                }
                $pct = (float) $student->discount_pct;
                Charge::create([
                    'tenant_id'   => $plan->tenant_id,
                    'student_id'  => $student->id,
                    'year_id'     => $plan->year_id,
                    'fee_plan_id' => $plan->id,
                    'kind'        => $plan->kind,
                    'concept'     => $concept,
                    'period'      => $period,
                    'due_on'      => $due->toDateString(),
                    'amount'      => $plan->amount,
                    'discount'    => $pct > 0 ? round((float) $plan->amount * $pct / 100, 2) : 0,
                    'status'      => 'pending',
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** Un alumno que se inscribe a mitad de año recibe las cuotas de los conceptos de su curso. */
    public function generateForStudent(Student $student): int
    {
        $n = 0;
        FeePlan::where('tenant_id', $student->tenant_id)->where('is_active', true)->get()->each(function (FeePlan $p) use ($student, &$n) {
            $applies = (!$p->course_id || $p->course_id === $student->course_id)
                && (!$p->level || $p->level === $student->course?->level);
            if ($applies) {
                $n += $this->generate($p, $student);
            }
        });

        return $n;
    }

    protected function yearNumber(FeePlan $plan): int
    {
        $year = $plan->year;
        if ($year?->starts_on) {
            return (int) $year->starts_on->format('Y');
        }
        if ($year && preg_match('/(20\d{2})/', $year->name, $m)) {
            return (int) $m[1];
        }

        return (int) now()->format('Y');
    }

    /** @return array<int, array{0:string,1:Carbon,2:string}> */
    protected function installmentsFor(FeePlan $plan, int $year): array
    {
        $out = [];
        for ($i = 0; $i < $plan->installments; $i++) {
            $month = $plan->first_month + $i;
            $y = $year + intdiv($month - 1, 12);
            $m = (($month - 1) % 12) + 1;
            $due = Carbon::create($y, $m, 1)->setDay(min($plan->due_day, Carbon::create($y, $m, 1)->daysInMonth));
            $concept = $plan->installments > 1 ? ucfirst(self::MONTHS[$m]) . ' ' . $y . ' — ' . $plan->name : $plan->name;
            $out[] = [sprintf('%04d-%02d', $y, $m), $due, $concept];
        }

        return $out;
    }

    /**
     * Registra un pago y lo reparte entre las cuotas (las indicadas o, si no hay
     * ninguna, de la más antigua a la más reciente). Devuelve el recibo.
     *
     * @param array<int,int>|null $chargeIds
     */
    public function recordPayment(Student $student, float $amount, string $method = 'cash', ?array $chargeIds = null, ?string $reference = null,
        ?Guardian $guardian = null, ?string $notes = null, ?int $userId = null): Payment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new SchoolException('El monto debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($student, $amount, $method, $chargeIds, $reference, $guardian, $notes, $userId) {
            $q = Charge::where('student_id', $student->id)->whereIn('status', ['pending', 'partial'])->lockForUpdate()->orderBy('due_on')->orderBy('id');
            if ($chargeIds !== null) {
                $q->whereIn('id', $chargeIds);
            }
            $charges = $q->get();
            $totalDue = round($charges->sum(fn (Charge $c) => $c->due), 2);
            if ($charges->isEmpty() || $totalDue <= 0) {
                throw new SchoolException('No hay cuotas pendientes para cobrar.');
            }
            if ($amount > $totalDue + 0.001) {
                throw new SchoolException('El monto (' . number_format($amount, 2) . ') supera lo adeudado (' . number_format($totalDue, 2) . ').');
            }

            $receipt = (int) Payment::where('tenant_id', $student->tenant_id)->lockForUpdate()->max('receipt_no') + 1;
            $payment = Payment::create([
                'tenant_id'   => $student->tenant_id,
                'student_id'  => $student->id,
                'guardian_id' => $guardian?->id,
                'receipt_no'  => $receipt,
                'amount'      => $amount,
                'method'      => $method,
                'reference'   => $reference,
                'paid_at'     => now(),
                'received_by' => $userId,
                'notes'       => $notes,
            ]);

            $left = $amount;
            foreach ($charges as $c) {
                if ($left <= 0) {
                    break;
                }
                $apply = min($left, $c->due);
                PaymentItem::create(['payment_id' => $payment->id, 'charge_id' => $c->id, 'amount' => $apply]);
                $c->paid_amount = round((float) $c->paid_amount + $apply, 2);
                $full = $c->paid_amount >= $c->net - 0.001;
                $c->status = $full ? 'paid' : 'partial';
                $c->paid_at = $full ? now() : null;
                $c->save();
                $left = round($left - $apply, 2);
            }

            Event::fire('aero.school.paymentRecorded', [$payment]);

            return $payment->load('items.charge', 'student');
        });
    }

    /** Anula un pago y devuelve las cuotas a su estado anterior. */
    public function voidPayment(Payment $payment, ?string $reason = null): Payment
    {
        if ($payment->voided_at) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $reason) {
            foreach ($payment->items()->with('charge')->get() as $item) {
                if ($c = $item->charge) {
                    $c->paid_amount = max(0, round((float) $c->paid_amount - (float) $item->amount, 2));
                    $c->status = $c->paid_amount > 0 ? 'partial' : 'pending';
                    $c->paid_at = null;
                    $c->save();
                }
            }
            $payment->voided_at = now();
            $payment->notes = trim($payment->notes . ' [Anulado' . ($reason ? ': ' . $reason : '') . ']');
            $payment->save();
            Event::fire('aero.school.paymentVoided', [$payment]);

            return $payment;
        });
    }

    public function voidCharge(Charge $charge): Charge
    {
        if ($charge->paid_amount > 0) {
            throw new SchoolException('La cuota ya tiene pagos: anule primero el pago.');
        }
        $charge->update(['status' => 'void']);

        return $charge;
    }

    /** Estado de cuenta: cuotas (todas las de la gestión) y pagos con saldo corrido. */
    public function statement(Student $student, ?int $yearId = null): array
    {
        $charges = $student->charges()->when($yearId, fn ($q) => $q->where('year_id', $yearId))
            ->where('status', '!=', 'void')->orderBy('due_on')->orderBy('id')->get();
        $payments = $student->payments()->whereNull('voided_at')->orderBy('paid_at')->get();

        return [
            'charges'  => $charges,
            'payments' => $payments,
            'billed'   => round($charges->sum(fn ($c) => $c->net), 2),
            'paid'     => round((float) $charges->sum('paid_amount'), 2),
            'balance'  => round($charges->sum(fn ($c) => $c->due), 2),
            'overdue'  => round($charges->filter(fn ($c) => $c->is_overdue)->sum(fn ($c) => $c->due), 2),
        ];
    }

    /** QR de cobro de aero/pay para UNA cuota. Null si no hay Pay o cuenta configurada. */
    public function issueQr(Charge $c): ?\Aero\Pay\Models\QrCode
    {
        if (!class_exists(\Aero\Pay\Classes\QrIssuer::class) || !in_array($c->status, ['pending', 'partial'], true)) {
            return null;
        }

        if ($c->payment_reference) {
            $existing = \Aero\Pay\Models\QrCode::where('internal_reference', $c->payment_reference)->first();
            if ($existing && $existing->status === 'pending') {
                return $existing;
            }
        }

        $settings = Settings::where('tenant_id', $c->tenant_id)->first();
        $bank = $settings?->bank_account_id ? \Aero\Pay\Models\BankAccount::active()->find($settings->bank_account_id) : null;
        if (!$bank) {
            return null;
        }

        try {
            $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $bank,
                amount: (float) $c->due,
                currency: $settings->currency ?: 'BOB',
                description: $c->concept . ' — ' . ($c->student?->full_name ?? ''),
                externalReference: 'school-cuota-' . $c->id . '-' . now()->timestamp,
                origin: 'backend',
                dueDate: now()->addDays(30)->toDateString(),
                tenantId: $c->tenant_id,
            );
        } catch (\Throwable $e) {
            \Log::warning('[school] No se pudo emitir el QR: ' . $e->getMessage());

            return null;
        }

        $c->payment_reference = $qr->internal_reference;
        $c->save();

        return $qr;
    }

    /** Registra como pagadas las cuotas cuyo QR ya se cobró (listener de QrCode + respaldo por schedule). */
    public function syncQrPayments(): int
    {
        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return 0;
        }

        $n = 0;
        Charge::open()->whereNotNull('payment_reference')->with('student')->get()->each(function (Charge $c) use (&$n) {
            $paid = \Aero\Pay\Models\QrCode::where('internal_reference', $c->payment_reference)->where('status', 'paid')->exists();
            if ($paid && $c->student) {
                try {
                    $this->recordPayment($c->student, $c->due, 'qr', [$c->id], $c->payment_reference, null, 'Cobro automático por QR');
                    $n++;
                } catch (SchoolException $e) {
                    // ya cobrada por otra vía
                }
            }
        });

        return $n;
    }
}
