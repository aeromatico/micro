<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Tenant;
use DB;

/**
 * Reservas del alta pública: un Tenant en 'pending_payment' bloquea su handle
 * mientras se paga. La sesión del navegador recuerda cuáles creó, para que
 * quien vuelve atrás (o recarga) a cambiar rubro o plan no choque contra su
 * propia reserva.
 */
class SignupReservations
{
    protected const SESSION_KEY = 'aero_sites_signup_reservations';

    public static function remember(Tenant $tenant): void
    {
        session()->put(self::SESSION_KEY . '.' . $tenant->id, $tenant->signup_payment_reference);
    }

    public static function ownedByVisitor(Tenant $tenant): bool
    {
        return $tenant->status === 'pending_payment'
            && $tenant->signup_payment_reference
            && session(self::SESSION_KEY . '.' . $tenant->id) === $tenant->signup_payment_reference;
    }

    /**
     * Anula el QR y purga la reserva. Devuelve false, sin tocar nada, si el
     * QR ya está pagado.
     */
    public static function release(Tenant $tenant): bool
    {
        if (static::isPaid($tenant)) {
            return false;
        }

        static::cancelQr($tenant);

        // El pago pudo confirmarse mientras se llamaba al banco.
        if (static::isPaid($tenant)) {
            return false;
        }

        $tenant->purge();

        return true;
    }

    /**
     * Anula el QR contra el banco antes de purgar el tenant — sin esto
     * quedaría "pending" del lado del banco hasta su propio vencimiento (por
     * defecto varios días, ver QrboSettings::default_due_date_days), mucho
     * más largo que la ventana real que tuvo el visitante para pagar. Nunca
     * bloquea la liberación del handle: si el banco no soporta anular (QR
     * estático) o la llamada falla, se sigue igual.
     */
    public static function cancelQr(Tenant $tenant): void
    {
        if (!$tenant->signup_qr_code_id || !class_exists(\Aero\Pay\Models\QrCode::class)) {
            return;
        }

        $qrCode = \Aero\Pay\Models\QrCode::find($tenant->signup_qr_code_id);
        if (!$qrCode || $qrCode->status !== 'pending') {
            return;
        }

        try {
            $driver = app(\Aero\Pay\Classes\PaymentDriverManager::class)->make($qrCode->bank_code);
            $driver->cancelQr($qrCode->bankAccount, $qrCode->external_qr_id);
        } catch (\Throwable $e) {
            \Log::info("Aero.Sites: no se pudo anular el QR de alta #{$qrCode->id} contra el banco (se marca cancelado igual): " . $e->getMessage());
        }

        // Con lock, igual que QrCode::markPaid(): nunca pisar un 'paid'.
        DB::transaction(function () use ($qrCode) {
            $row = \Aero\Pay\Models\QrCode::whereKey($qrCode->id)->lockForUpdate()->first();
            if ($row && $row->status === 'pending') {
                $row->status = 'cancelled';
                $row->save();
            }
        });
    }

    protected static function isPaid(Tenant $tenant): bool
    {
        return $tenant->signup_qr_code_id
            && class_exists(\Aero\Pay\Models\QrCode::class)
            && \Aero\Pay\Models\QrCode::whereKey($tenant->signup_qr_code_id)->value('status') === 'paid';
    }
}
