<?php namespace Aero\Sheets\Classes;

use Aero\Sheets\Models\Mapping;
use Aero\Sheets\Models\Run;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta un mapeo: importa filas de la hoja al modelo, o exporta el modelo a
 * la hoja. Solo toca los campos autorizados en la fuente y, al exportar, solo
 * las columnas mapeadas (el resto de la hoja queda intacto).
 */
class SyncService
{
    /** Tope por ejecución cuando el mapeo no fija «máx. filas». */
    const HARD_LIMIT = 10000;
    const MAX_ERRORS = 50;

    public function __construct(protected SheetsClient $client)
    {
    }

    public function run(Mapping $m, $user, bool $dryRun = false): Run
    {
        $run = Run::create([
            'mapping_id'      => $m->id,
            'tenant_id'       => $m->tenant_id,
            'backend_user_id' => $user?->id,
            'direction'       => $m->direction,
            'dry_run'         => $dryRun,
            'status'          => 'running',
            'started_at'      => now(),
        ]);

        $stats = ['rows_read' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $errors = [];

        try {
            $this->guard($m);
            $m->direction === 'export'
                ? $this->export($m, $dryRun, $stats, $errors)
                : $this->import($m, $dryRun, $stats, $errors);

            $status = $stats['failed'] ? ($stats['created'] + $stats['updated'] ? 'partial' : 'failed') : 'ok';
        } catch (\Throwable $e) {
            $status = 'failed';
            $errors[] = $e->getMessage();
        }

        $run->update($stats + ['status' => $status, 'errors' => array_slice($errors, 0, self::MAX_ERRORS), 'finished_at' => now()]);

        if (!$dryRun) {
            $m->forceFill(['last_run_at' => now(), 'last_status' => $status])->saveQuietly();
        }

        return $run;
    }

    protected function guard(Mapping $m): void
    {
        $source = $m->source;
        if (!$source || !$source->is_enabled || !SourceRegistry::isAllowedClass($source->model_class)) {
            throw new SheetsException('La fuente de datos no está disponible.');
        }
        if ($m->tenant_id && (!$source->tenant_access || !SourceRegistry::hasTenantColumn($source->model_class))) {
            throw new SheetsException('Esta fuente no está abierta a tenants.');
        }
        if (!$m->spreadsheet_id || !$m->sheet_title) {
            throw new SheetsException('Falta elegir la hoja de cálculo y su pestaña.');
        }
    }

    protected function query(Mapping $m)
    {
        $class = $m->source->model_class;
        $q = $class::query();

        return $m->tenant_id ? $q->where('tenant_id', $m->tenant_id) : $q;
    }

    /** @return array<int, array{field: string, ref: string}> solo campos autorizados */
    protected function columns(Mapping $m, array $allowed): array
    {
        $out = [];
        foreach ((array) $m->columns as $c) {
            $field = $c['field'] ?? null;
            $ref = trim((string) ($c['column'] ?? ''));
            if ($field && $ref !== '' && isset($allowed[$field])) {
                $out[] = ['field' => $field, 'ref' => $ref];
            }
        }

        if (!$out) {
            throw new SheetsException('El mapeo no tiene columnas válidas (revisa los campos autorizados de la fuente).');
        }

        return $out;
    }

    protected function headers(Mapping $m): array
    {
        if ($m->header_row < 1) {
            return [];
        }

        return $this->client->get($m->spreadsheet_id, SheetsClient::a1($m->sheet_title, "{$m->header_row}:{$m->header_row}"))[0] ?? [];
    }

    protected function import(Mapping $m, bool $dryRun, array &$stats, array &$errors): void
    {
        $allowed = $m->source->fieldMap('import');
        $columns = $this->columns($m, $allowed);
        $headers = $this->headers($m);

        foreach ($columns as &$c) {
            $c['index'] = ColumnResolver::index($c['ref'], $headers);
            if ($c['index'] === null) {
                throw new SheetsException("No encuentro la columna «{$c['ref']}» en la fila de cabeceras.");
            }
        }
        unset($c);

        $key = $m->key_field ?: null;
        if ($m->import_mode !== 'create' && !$key) {
            throw new SheetsException('Para actualizar hace falta un campo clave que identifique cada registro.');
        }
        if ($key && !in_array($key, array_column($columns, 'field'), true)) {
            throw new SheetsException("El campo clave «{$key}» debe estar entre las columnas mapeadas.");
        }

        $start = max(1, (int) $m->start_row);
        $limit = min($m->max_rows ?: self::HARD_LIMIT, self::HARD_LIMIT);
        $rows = $this->client->get($m->spreadsheet_id, SheetsClient::a1($m->sheet_title, "A{$start}:ZZ" . ($start + $limit - 1)));

        $class = $m->source->model_class;
        $dryRun && DB::beginTransaction();

        try {
            foreach ($rows as $offset => $row) {
                $line = $start + $offset;
                $values = [];
                foreach ($columns as $c) {
                    $values[$c['field']] = $row[$c['index']] ?? '';
                }

                if (!array_filter($values, fn ($v) => trim((string) $v) !== '')) {
                    continue; // fila vacía
                }
                $stats['rows_read']++;

                try {
                    $attrs = [];
                    foreach ($values as $field => $raw) {
                        $attrs[$field] = $this->coerce($allowed[$field]['type'] ?? 'string', $raw);
                    }

                    $existing = null;
                    if ($key) {
                        if (($attrs[$key] ?? null) === null) {
                            throw new \InvalidArgumentException("la clave «{$key}» está vacía");
                        }
                        $existing = $this->query($m)->where($key, $attrs[$key])->first();
                    }

                    if (($existing && $m->import_mode === 'create') || (!$existing && $m->import_mode === 'update')) {
                        $stats['skipped']++;
                        continue;
                    }

                    $model = $existing ?? new $class;
                    // Los campos del sistema (id, tenant_id, fechas) nunca se escriben desde la hoja.
                    $model->forceFill(array_diff_key($attrs, array_flip(SourceRegistry::READONLY)));
                    if (!$existing && $m->tenant_id) {
                        $model->tenant_id = $m->tenant_id;
                    }
                    $model->save();

                    $existing ? $stats['updated']++ : $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $errors[] = "Fila {$line}: " . $e->getMessage();
                }
            }
        } finally {
            $dryRun && DB::rollBack();
        }
    }

    protected function export(Mapping $m, bool $dryRun, array &$stats, array &$errors): void
    {
        $allowed = $m->source->fieldMap('export');
        $columns = $this->columns($m, $allowed);
        $headers = $this->headers($m);

        // Posición de cada columna: existente por cabecera/letra; si no, se añade al final.
        $next = $headers ? count($headers) : 0;
        $newHeaders = [];
        foreach ($columns as &$c) {
            $c['index'] = ColumnResolver::index($c['ref'], $headers);
            if ($c['index'] === null) {
                if (!$m->write_headers || $m->header_row < 1) {
                    throw new SheetsException("No encuentro la columna «{$c['ref']}» y no se están escribiendo cabeceras.");
                }
                $c['index'] = $next++;
                $newHeaders[$c['index']] = true;
            }
        }
        unset($c);

        $limit = min($m->max_rows ?: self::HARD_LIMIT, self::HARD_LIMIT);
        $key = ($m->key_field && isset($allowed[$m->key_field])) ? $m->key_field : array_key_first($allowed);
        $records = $this->query($m)->orderBy($key)->limit($limit)->get();

        $stats['rows_read'] = $records->count();
        $stats['updated'] = $records->count();

        if ($dryRun) {
            return;
        }

        $start = max(1, (int) $m->start_row);
        $end = $start + max(0, $records->count() - 1);
        $data = [];
        $clear = [];

        foreach ($columns as $c) {
            $letter = SheetsClient::colLetter($c['index']);

            if ($m->write_headers && $m->header_row >= 1 && (isset($newHeaders[$c['index']]) || !isset($headers[$c['index']]))) {
                $data[SheetsClient::a1($m->sheet_title, "{$letter}{$m->header_row}")] = [[$allowed[$c['field']]['label'] ?? $c['field']]];
            }
            if ($m->clear_before_export) {
                $clear[] = SheetsClient::a1($m->sheet_title, "{$letter}{$start}:{$letter}");
            }
            if ($records->isNotEmpty()) {
                $data[SheetsClient::a1($m->sheet_title, "{$letter}{$start}:{$letter}{$end}")] =
                    $records->map(fn ($r) => [$this->present($allowed[$c['field']]['type'] ?? 'string', $r->getAttribute($c['field']))])->all();
            }
        }

        $this->client->batchClear($m->spreadsheet_id, $clear);
        $this->client->batchUpdate($m->spreadsheet_id, $data);
    }

    public function coerce(string $type, $raw)
    {
        $raw = is_string($raw) ? trim($raw) : $raw;
        if ($raw === '' || $raw === null) {
            return null;
        }

        switch ($type) {
            case 'bool':
                $v = mb_strtolower((string) $raw);
                if (in_array($v, ['1', 'true', 'verdadero', 'si', 'sí', 'yes', 'x', 'activo'], true)) {
                    return true;
                }
                if (in_array($v, ['0', 'false', 'falso', 'no', 'inactivo'], true)) {
                    return false;
                }
                throw new \InvalidArgumentException("«{$raw}» no es un valor Sí/No");
            case 'int':
            case 'decimal':
                $n = str_replace([' ', "\u{00A0}"], '', (string) $raw);
                if (str_contains($n, ',') && str_contains($n, '.')) {
                    // El último separador es el decimal: 1.234,56 o 1,234.56
                    $n = strrpos($n, ',') > strrpos($n, '.')
                        ? str_replace(',', '.', str_replace('.', '', $n))
                        : str_replace(',', '', $n);
                } else {
                    $n = str_replace(',', '.', $n);
                }
                if (!is_numeric($n)) {
                    throw new \InvalidArgumentException("«{$raw}» no es un número");
                }

                return $type === 'int' ? (int) $n : $n + 0;
            case 'date':
            case 'datetime':
                try {
                    $c = \Illuminate\Support\Carbon::parse((string) $raw);
                } catch (\Throwable) {
                    throw new \InvalidArgumentException("«{$raw}» no es una fecha válida");
                }

                return $type === 'date' ? $c->format('Y-m-d') : $c->format('Y-m-d H:i:s');
            case 'json':
                $d = json_decode((string) $raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('JSON inválido');
                }

                return $d;
        }

        return (string) $raw;
    }

    public function present(string $type, $value)
    {
        return match (true) {
            $value === null => '',
            $value instanceof \DateTimeInterface => $value->format($type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s'),
            is_bool($value) => $value,
            is_array($value), is_object($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => $value,
        };
    }
}
