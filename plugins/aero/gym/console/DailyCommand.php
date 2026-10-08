<?php namespace Aero\Gym\Console;

use Aero\Gym\Classes\MembershipService;
use Aero\Gym\Classes\Reminders;
use Illuminate\Console\Command;

/** gym:daily — cobros recibidos, vencimientos y recordatorios por WhatsApp. */
class DailyCommand extends Command
{
    protected $signature = 'gym:daily';

    protected $description = 'Sincroniza pagos, vence membresías y envía recordatorios de vencimiento.';

    public function handle(MembershipService $memberships, Reminders $reminders): int
    {
        $paid = $memberships->syncPayments();
        $expired = $memberships->expireDue();
        $sent = $reminders->run();
        $this->info("Pagos activados: {$paid}. Vencidas: {$expired}. Recordatorios enviados: {$sent}.");

        return self::SUCCESS;
    }
}
