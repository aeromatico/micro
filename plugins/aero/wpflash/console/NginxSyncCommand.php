<?php namespace Aero\WpFlash\Console;

use Illuminate\Console\Command;
use Aero\WpFlash\Classes\DomainRouter;

/**
 * Red de seguridad de DomainRouter::regenerateNginxConfig() (que ya corre en
 * cada activar/desactivar) — por si ese archivo se pierde o queda
 * desactualizado por cualquier motivo. No toca nginx directamente: solo
 * regenera storage/app/wpflash/tenants.conf (ver deploy/apply-nginx.sh).
 */
class NginxSyncCommand extends Command
{
    protected $signature = 'wpflash:nginx-sync';

    protected $description = 'Regenera storage/app/wpflash/tenants.conf con los dominios de tenants WP Flash activos.';

    public function handle(): int
    {
        DomainRouter::regenerateNginxConfig();

        return self::SUCCESS;
    }
}
