<?php namespace Aero\Credits\Console;

use Aero\Credits\Classes\Credits;
use Illuminate\Console\Command;

class Reconcile extends Command
{
    protected $signature = 'credits:reconcile {--tenant= : Solo este tenant_id} {--fix : Corregir el saldo cacheado al valor del ledger}';

    protected $description = 'Compara el saldo cacheado de cada cuenta contra SUM(delta) del ledger.';

    public function handle(): int
    {
        $tenant = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $issues = Credits::reconcile($tenant, (bool) $this->option('fix'));

        if (!$issues) {
            $this->info('Todas las cuentas cuadran con el ledger.');
            return self::SUCCESS;
        }

        $this->table(['cuenta', 'tenant', 'color', 'cacheado', 'ledger', 'dif.'], array_map(fn ($i) => array_values($i), $issues));
        $this->{$this->option('fix') ? 'info' : 'error'}(count($issues) . ($this->option('fix') ? ' cuenta(s) corregidas.' : ' cuenta(s) descuadradas (usa --fix para corregir).'));

        return $this->option('fix') ? self::SUCCESS : self::FAILURE;
    }
}
