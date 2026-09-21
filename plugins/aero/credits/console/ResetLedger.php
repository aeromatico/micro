<?php namespace Aero\Credits\Console;

use Aero\Credits\Classes\LedgerGuard;
use Illuminate\Console\Command;

class ResetLedger extends Command
{
    protected $signature = 'credits:reset-ledger {--force : Confirmar sin preguntar}';

    protected $description = 'PELIGRO: borra TODO el libro de créditos (movimientos, cuentas, holds, compras, acumulados). Solo para pruebas.';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Esto borra TODOS los movimientos, saldos y compras de créditos y no se puede deshacer. ¿Continuar?')) {
            return self::FAILURE;
        }

        LedgerGuard::resetAll();
        $this->info('Libro de créditos vaciado y garantías reinstaladas.');

        return self::SUCCESS;
    }
}
