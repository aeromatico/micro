<?php namespace Aero\Medical\Console;

use Aero\Medical\Models\Cie10;
use Illuminate\Console\Command;

/** Carga/actualiza el catálogo global CIE-10 desde data/cie10_es.tsv (idempotente, por código). */
class ImportCie10 extends Command
{
    protected $signature = 'medical:import-cie10 {file? : TSV (code, level, parent, chapter, title); por defecto data/cie10_es.tsv}';

    protected $description = 'Importa el catálogo CIE-10 (OPS/OMS, español) a la tabla global de Historia clínica';

    public function handle(): int
    {
        $file = $this->argument('file') ?: dirname(__DIR__) . '/data/cie10_es.tsv';
        if (!is_file($file)) {
            $this->error("No existe {$file}");

            return 1;
        }

        $fh = fopen($file, 'r');
        $head = fgetcsv($fh, 0, "\t");
        if ($head !== ['code', 'level', 'parent', 'chapter', 'title']) {
            $this->error('Cabecera inesperada: se esperaba code, level, parent, chapter, title.');

            return 1;
        }

        $batch = [];
        $n = 0;
        while (($r = fgetcsv($fh, 0, "\t")) !== false) {
            if (count($r) !== 5 || !preg_match('/^[A-Z]\d{2}(\.\d)?$/', $r[0]) || trim($r[4]) === '') {
                $this->warn('Fila omitida: ' . implode(' | ', $r));
                continue;
            }
            $batch[] = ['code' => $r[0], 'level' => (int) $r[1], 'parent' => $r[2] !== '' ? $r[2] : null, 'chapter' => (int) $r[3] ?: null, 'title' => $r[4]];
            if (count($batch) >= 500) {
                $n += $this->flush($batch);
                $batch = [];
            }
        }
        $n += $this->flush($batch);
        fclose($fh);

        $this->info("CIE-10: {$n} códigos cargados; total en la tabla: " . Cie10::count());

        return 0;
    }

    protected function flush(array $batch): int
    {
        if ($batch) {
            Cie10::upsert($batch, ['code'], ['level', 'parent', 'chapter', 'title']);
        }

        return count($batch);
    }
}
