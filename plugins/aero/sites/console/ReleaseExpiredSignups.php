<?php namespace Aero\Sites\Console;

use Aero\Sites\Classes\SignupReservations;
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
            SignupReservations::cancelQr($tenant);
            $tenant->purge();
        }

        $this->info($expired->count() . ' alta(s) liberada(s).');

        return self::SUCCESS;
    }
}
