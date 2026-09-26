<?php namespace Aero\Hub\Console;

use Aero\Hub\Classes\CatalogSync;
use Illuminate\Console\Command;

/**
 * Upsert del catálogo de endpoints desde el spec de YepAPI (snapshot local, o
 * re-descargado con --fetch). Nunca toca precio/activación de filas
 * existentes — ver CatalogSync.
 */
class SyncCatalog extends Command
{
    protected $signature = 'hub:sync-catalog {--fetch : Re-descargar el spec desde docs.yepapi.com/openapi.json antes de sincronizar}';

    protected $description = 'Sincroniza el catálogo de endpoints de Aero.Hub desde el spec OpenAPI de YepAPI';

    public function handle(): int
    {
        $result = CatalogSync::run($this->option('fetch'));

        $this->info("Aero.Hub: catálogo sincronizado — {$result['created']} nuevos, {$result['updated']} actualizados, {$result['total']} en total.");

        return 0;
    }
}
