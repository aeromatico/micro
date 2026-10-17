<?php namespace Aero\Medical\Console;

use Aero\Medical\Models\Liname;
use Illuminate\Console\Command;

/**
 * Carga LINAME desde un CSV VERIFICADO (columnas: code,name,form,concentration,atc,restricted).
 * No hay archivo por defecto: el PDF oficial solo trae OCR con errores (ver data/README.md).
 */
class ImportLiname extends Command
{
    protected $signature = 'medical:import-liname {file : CSV verificado} {--replace : Vacía la tabla antes de cargar}';

    protected $description = 'Importa la LINAME (Bolivia) desde un CSV verificado a la tabla global';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!is_file($file)) {
            $this->error("No existe {$file}");

            return 1;
        }

        $fh = fopen($file, 'r');
        $head = fgetcsv($fh);
        if ($head !== ['code', 'name', 'form', 'concentration', 'atc', 'restricted']) {
            $this->error('Cabecera inesperada: se esperaba code,name,form,concentration,atc,restricted.');

            return 1;
        }

        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count($r) !== 6 || trim($r[1]) === '') {
                $this->warn('Fila omitida: ' . implode(' | ', $r));
                continue;
            }
            $rows[] = ['code' => $r[0] ?: null, 'name' => $r[1], 'form' => $r[2] ?: null, 'concentration' => $r[3] ?: null, 'atc' => $r[4] ?: null, 'restricted' => in_array(strtolower($r[5]), ['1', 'r', 'si', 'sí', 'true'], true)];
        }
        fclose($fh);

        if ($this->option('replace')) {
            Liname::query()->delete();
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Liname::insert($chunk);
        }

        $this->info('LINAME: ' . count($rows) . ' filas cargadas; total en la tabla: ' . Liname::count());

        return 0;
    }
}
