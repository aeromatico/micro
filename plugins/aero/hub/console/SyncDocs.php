<?php namespace Aero\Hub\Console;

use Aero\Hub\Classes\DocsSync;
use Illuminate\Console\Command;

/**
 * Genera/actualiza el artículo de Aero.Docs de cada HubEndpoint activo (y
 * despublica el de cualquiera que haya quedado inactivo) — ver DocsSync.
 * `HubEndpoint::afterSave()` ya lo hace fila por fila al guardar desde el
 * backend; este comando es para el primer poblado masivo o para
 * regenerar todo si cambia el formato del artículo.
 */
class SyncDocs extends Command
{
    protected $signature = 'hub:sync-docs';

    protected $description = 'Genera/actualiza la documentación de Aero.Docs para el catálogo de Aero.Hub';

    public function handle(): int
    {
        $result = DocsSync::runAll();

        $this->info("Aero.Hub: documentación sincronizada — {$result['created']} creados, {$result['updated']} actualizados, {$result['unpublished']} despublicados/sin cambios.");

        return 0;
    }
}
