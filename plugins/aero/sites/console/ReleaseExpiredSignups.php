<?php namespace Aero\Sites\Console;

use Aero\Sites\Models\Settings;
use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Libera handles reservados por /alta cuyo pago nunca llegó — un tenant
 * queda en 'pending_payment' apenas se elige subdominio (ver
 * SignupWizard::onCreateSignup) para bloquear el handle mientras se paga.
 * Pasado signup_payment_ttl_minutes sin confirmación (unos pocos minutos,
 * es un pago inmediato con la app del banco en la mano), se anula el QR
 * contra el banco y se purga el tenant por completo para que el nombre
 * vuelva a estar disponible. Corre cada minuto — ver Plugin::registerSchedule().
 */
class ReleaseExpiredSignups extends Command
{
    protected $signature = 'aero.sites:release-expired-signups';

    protected $description = 'Anula el QR y purga tenants con alta reservada (pending_payment) que nunca completaron el pago.';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(Settings::getSignupPaymentTtlMinutes());

        $expired = Tenant::where('status', 'pending_payment')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($expired as $tenant) {
            $this->info("Liberando \"{$tenant->handle}\" (reservado " . $tenant->created_at->diffForHumans() . ')');
            $this->cancelSignupQr($tenant);
            $tenant->purge();
        }

        $this->info($expired->count() . ' alta(s) liberada(s).');

        return self::SUCCESS;
    }

    /**
     * Anula el QR contra el banco antes de purgar el tenant — sin esto
     * quedaría "pending" del lado del banco hasta su propio vencimiento (por
     * defecto varios días, ver QrboSettings::default_due_date_days), mucho
     * más largo que la ventana real que tuvo el visitante para pagar. Nunca
     * bloquea la liberación del handle: si el banco no soporta anular (QR
     * estático) o la llamada falla, se sigue igual.
     */
    protected function cancelSignupQr(Tenant $tenant): void
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

        $qrCode->update(['status' => 'cancelled']);
    }
}
