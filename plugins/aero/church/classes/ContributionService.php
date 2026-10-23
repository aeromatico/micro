<?php namespace Aero\Church\Classes;

use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Contribution;
use Illuminate\Support\Facades\DB;

/** Cobro por QR de Pay, conciliación y numeración de comprobantes. */
class ContributionService
{
    public function payAvailable(): bool
    {
        return class_exists(\Aero\Pay\Classes\QrIssuer::class) && class_exists(\Aero\Pay\Models\QrCode::class);
    }

    /** Emite (o reutiliza) el QR de un aporte pendiente. */
    public function issueQr(Contribution $c): \Aero\Pay\Models\QrCode
    {
        if (!$this->payAvailable()) {
            throw new ChurchException('Los pagos con QR requieren el módulo de Pagos.');
        }
        if ($c->method !== 'qr' || $c->status !== 'pending') {
            throw new ChurchException('Solo un aporte por QR pendiente puede generar un QR.');
        }

        if ($c->payment_reference) {
            $existing = \Aero\Pay\Models\QrCode::where('internal_reference', $c->payment_reference)->first();
            if ($existing && $existing->status === 'pending') {
                return $existing;
            }
        }

        $settings = ChurchSettings::where('tenant_id', $c->tenant_id)->first();
        $bank = $settings?->bank_account_id
            ? \Aero\Pay\Models\BankAccount::active()->forTenant((int) $c->tenant_id)->find($settings->bank_account_id)
            : null;
        if (!$bank) {
            throw new ChurchException('Elige una cuenta bancaria activa en Aportes → Configuración.');
        }

        try {
            $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $bank,
                amount: (float) $c->amount,
                currency: $c->currency ?: 'BOB',
                description: $c->fund?->name ?: 'Aporte',
                externalReference: 'church-aporte-' . $c->id . '-' . now()->timestamp,
                origin: 'backend',
                dueDate: now()->addDays(7)->toDateString(),
                tenantId: $c->tenant_id,
            );
        } catch (\Throwable $e) {
            \Log::warning('[church] No se pudo emitir el QR: ' . $e->getMessage());
            throw new ChurchException('No se pudo generar el QR. Revisa la cuenta bancaria en Pagos.');
        }

        $c->payment_reference = $qr->internal_reference;
        $c->save();

        return $qr;
    }

    /** Listener de aero.pay.qrStatusChanged: solo actúa sobre QR enlazados a un aporte. */
    public function onQrStatusChanged($qr): void
    {
        if (!$qr || empty($qr->internal_reference)) {
            return;
        }

        $c = Contribution::where('payment_reference', $qr->internal_reference)->where('tenant_id', $qr->tenant_id)->first();
        if (!$c) {
            return;
        }
        // Si se anuló mientras el QR seguía vivo y igual se pagó, el dinero entró: se reabre y se confirma.
        if ($c->status === 'cancelled' && $qr->status === 'paid') {
            \Log::warning('[church] QR pagado de un aporte anulado; se confirma: aporte #' . $c->id);
            $c->status = 'pending';
            $c->save();
        }
        if ($c->status !== 'pending') {
            return;
        }

        if ($qr->status === 'paid') {
            $c->markConfirmed();
        } elseif (in_array($qr->status, ['cancelled', 'expired'], true)) {
            $c->markCancelled();
        }
    }

    /** Respaldo del listener: concilia los QR pagados que quedaron pendientes. */
    public function syncPayments(): int
    {
        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return 0;
        }

        $n = 0;
        Contribution::where('status', 'pending')->where('method', 'qr')->whereNotNull('payment_reference')->get()
            ->each(function (Contribution $c) use (&$n) {
                if (\Aero\Pay\Models\QrCode::where('internal_reference', $c->payment_reference)->where('tenant_id', $c->tenant_id)->where('status', 'paid')->exists()) {
                    $c->markConfirmed() && $n++;
                }
            });

        return $n;
    }

    /** Asigna (una sola vez) el número de comprobante. Los anónimos no tienen a quién emitirlo. */
    public function receiptNumber(Contribution $c): string
    {
        if ($c->status !== 'confirmed') {
            throw new ChurchException('Solo un aporte confirmado tiene comprobante.');
        }
        if ($c->is_anonymous) {
            throw new ChurchException('Un aporte anónimo no tiene comprobante.');
        }
        if ($c->receipt_number) {
            return $c->receipt_number;
        }

        $settingsId = ChurchSettings::forTenant($c->tenant_id)->id;

        return DB::transaction(function () use ($c, $settingsId) {
            $s = ChurchSettings::whereKey($settingsId)->lockForUpdate()->first();
            $fresh = Contribution::whereKey($c->id)->lockForUpdate()->first();
            if ($fresh->receipt_number) {
                return $c->receipt_number = $fresh->receipt_number;
            }

            $s->receipt_counter++;
            $s->save();

            Audit::record('updated', 'contribution', (int) $c->id, "Comprobante emitido para el aporte #{$c->id}", (int) $c->tenant_id, ['receipt_number']);
            $c->receipt_number = $s->receipt_prefix . '-' . str_pad((string) $s->receipt_counter, 6, '0', STR_PAD_LEFT);
            $c->save();

            return $c->receipt_number;
        });
    }
}
