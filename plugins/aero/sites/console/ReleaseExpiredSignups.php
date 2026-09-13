<?php namespace Aero\Sites\Console;

use Aero\Sites\Models\Settings;
use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Libera handles reservados por /alta cuyo pago nunca llegó — un tenant
 * queda en 'pending_payment' apenas se elige subdominio (ver
 * SignupWizard::onCreateSignup) para bloquear el handle mientras se paga.
 * Pasado signup_pending_ttl_hours sin confirmación, se purga por completo
 * para que el nombre vuelva a estar disponible.
 */
class ReleaseExpiredSignups extends Command
{
    protected $signature = 'aero.sites:release-expired-signups';

    protected $description = 'Purga tenants con alta reservada (pending_payment) que nunca completaron el pago.';

    public function handle(): int
    {
        $cutoff = now()->subHours(Settings::getSignupPendingTtlHours());

        $expired = Tenant::where('status', 'pending_payment')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($expired as $tenant) {
            $this->info("Liberando \"{$tenant->handle}\" (reservado " . $tenant->created_at->diffForHumans() . ')');
            $tenant->purge();
        }

        $this->info($expired->count() . ' alta(s) liberada(s).');

        return self::SUCCESS;
    }
}
