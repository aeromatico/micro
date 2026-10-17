<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Charge;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Patient;

/**
 * Cobros de la atención. Efectivo, transferencia u otro: quedan cobrados al registrarse. QR: se emite con
 * Aero.Pay (QrIssuer, origen «medical») y se concilia con su evento aero.pay.qrStatusChanged. Al cobrarse o
 * anularse se avisa por evento aero.medical.charge* con SOLO ids; Aero.Finance (si está) registra el ingreso.
 */
class ChargeService
{
    public const MAX_AMOUNT = 9999999.99;

    /** @param array{concept?:string,amount?:mixed,currency?:string,method?:string,consultation_id?:mixed,bank_account_id?:mixed} $in */
    public function create(Patient $p, array $in, $user): Charge
    {
        $tenantId = (int) $p->tenant_id;

        $amount = str_replace(',', '.', trim((string) ($in['amount'] ?? '')));
        if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > self::MAX_AMOUNT) {
            throw new MedicalException('Indique un monto válido mayor a cero.');
        }
        $currency = strtoupper(trim((string) ($in['currency'] ?? 'BOB')) ?: 'BOB');
        if (!in_array($currency, ['BOB', 'USD'], true)) {
            throw new MedicalException('Moneda no admitida (BOB o USD).');
        }
        $method = (string) ($in['method'] ?? 'cash');
        if (!isset(Charge::METHODS[$method])) {
            throw new MedicalException('Forma de cobro no válida.');
        }

        $consultationId = $bookingId = null;
        if (!empty($in['consultation_id'])) {
            $c = Consultation::where('tenant_id', $tenantId)->where('patient_id', $p->id)->find((int) $in['consultation_id']);
            if (!$c) {
                throw new MedicalException('La nota elegida no corresponde a este paciente.');
            }
            $consultationId = $c->id;
            $bookingId = $c->booking_id;
        }

        $concept = trim((string) ($in['concept'] ?? ''));

        $charge = new Charge([
            'tenant_id' => $tenantId, 'patient_id' => $p->id, 'consultation_id' => $consultationId, 'booking_id' => $bookingId,
            'concept' => mb_substr($concept !== '' ? $concept : 'Atención', 0, 255), 'amount' => round((float) $amount, 2),
            'currency' => $currency, 'method' => $method, 'registered_by' => $user->id,
        ]);
        $charge->status = 'pending';

        if ($method === 'qr') {
            $this->issueQr($charge, $p, (int) ($in['bank_account_id'] ?? 0));

            return $charge;
        }

        $charge->status = 'paid';
        $charge->paid_at = now();
        $charge->save();
        $this->fire('chargePaid', $charge);

        return $charge;
    }

    public static function payAvailable(): bool
    {
        return class_exists(\Aero\Pay\Classes\QrIssuer::class) && class_exists(\Aero\Pay\Models\BankAccount::class) && \Schema::hasTable('aero_pay_bank_accounts');
    }

    /** Cuentas del negocio con las que se puede emitir un QR. */
    public static function bankAccounts(int $tenantId): array
    {
        if (!static::payAvailable() || !$tenantId) {
            return [];
        }

        return \Aero\Pay\Models\BankAccount::active()->forTenant($tenantId)->get()->mapWithKeys(fn ($a) => [$a->id => trim(($a->label ?: 'Cuenta') . ' · ' . $a->bank_code)])->all();
    }

    protected function issueQr(Charge $charge, Patient $p, int $bankAccountId): void
    {
        if (!static::payAvailable()) {
            throw new MedicalException('Cobrar con QR requiere Aero.Pay (Bolivia Pay).');
        }
        $account = \Aero\Pay\Models\BankAccount::active()->forTenant((int) $p->tenant_id)->find($bankAccountId);
        if (!$account) {
            throw new MedicalException('Elija una cuenta bancaria activa del negocio para emitir el QR.');
        }

        // Primero el cobro (para tener su id como referencia); si el banco falla, no queda un cobro a medias.
        \DB::transaction(function () use ($charge, $account, $p) {
            $charge->bank_account_id = $account->id;
            $charge->save();

            try {
                $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                    bankAccount: $account,
                    amount: (float) $charge->amount,
                    currency: $charge->currency,
                    description: 'Atención #' . $charge->id,   // genérico: nada clínico viaja al banco
                    externalReference: 'medical-charge-' . $charge->id,
                    origin: 'medical',
                    tenantId: (int) $p->tenant_id,
                );
            } catch (\Throwable $e) {
                \Log::warning('Aero.Medical: no se pudo emitir el QR del cobro ' . $charge->id . ': ' . $e->getMessage());
                throw new MedicalException('No se pudo emitir el QR con el banco: ' . $e->getMessage());
            }

            $charge->qr_code_id = $qr->id;
            $charge->save();
        });
    }

    /** Cobro confirmado a mano (p. ej. el QR se pagó y el banco aún no concilió). */
    public function markPaid(Charge $c): Charge
    {
        if ($c->status !== 'pending') {
            throw new MedicalException('Solo se marca como cobrado un cobro pendiente.');
        }
        $c->status = 'paid';
        $c->paid_at = now();
        $c->save();
        $this->fire('chargePaid', $c);

        return $c;
    }

    public function cancel(Charge $c, ?string $reason = null): Charge
    {
        if ($c->status === 'cancelled') {
            throw new MedicalException('El cobro ya está anulado.');
        }
        $wasPaid = $c->status === 'paid';
        $c->status = 'cancelled';
        $c->cancel_reason = $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null;
        $c->save();

        if ($wasPaid) {
            $this->fire('chargeVoided', $c);
        }

        return $c;
    }

    /** Conciliación: Aero.Pay avisa el cambio de estado de un QR. Solo actúa sobre QR de este módulo. */
    public static function onQrStatus($qr): void
    {
        if (!$qr || ($qr->origin ?? null) !== 'medical') {
            return;
        }
        $c = Charge::where('qr_code_id', $qr->id)->first();
        if (!$c || $c->status !== 'pending') {
            return;
        }

        try {
            $svc = new static();
            match ($qr->status) {
                'paid'              => $svc->markPaid($c),
                'cancelled', 'expired' => $svc->cancel($c, $qr->status === 'expired' ? 'El QR venció sin pagarse' : 'QR cancelado'),
                default             => null,
            };
        } catch (\Throwable $e) {
            \Log::warning('Aero.Medical: no se pudo conciliar el cobro ' . $c->id . ': ' . $e->getMessage());
        }
    }

    protected function fire(string $event, Charge $c): void
    {
        // Solo ids: Aero.Api reenvía a webhooks lo que viaja en eventos aero.*.
        \Event::fire('aero.medical.' . $event, [['charge_id' => $c->id, 'patient_id' => $c->patient_id, 'tenant_id' => $c->tenant_id]]);
    }
}
