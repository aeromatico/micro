<?php namespace Aero\Crm\Console;

use Aero\Crm\Classes\Collections\CollectionReminderGenerator;
use Illuminate\Console\Command;

/**
 * crm:generate-cobranza-reminders — envía el recordatorio automático de
 * cobranza (WhatsApp vía Aero.Hello) a cada CollectionItem pendiente y
 * vencido que no haya sido recordado dentro del intervalo configurado por
 * tenant. Corre diariamente (ver Plugin::registerSchedule).
 */
class GenerateCollectionRemindersCommand extends Command
{
    protected $signature = 'crm:generate-cobranza-reminders';

    protected $description = 'Envía recordatorios automáticos de cobranza pendientes por WhatsApp.';

    public function handle(CollectionReminderGenerator $generator): int
    {
        $results = $generator->generateForAllTenants();
        $sent = $results->where('sent', true)->count();
        $skipped = $results->count() - $sent;

        $this->info("Recordatorios enviados: {$sent}. Omitidos (sin canal de WhatsApp vinculado): {$skipped}.");

        return self::SUCCESS;
    }
}
