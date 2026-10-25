<?php namespace Aero\Sites\Classes;

use Backend\Widgets\Lists;
use BackendAuth;
use Event;
use ApplicationException;

/**
 * Exportar a CSV o PDF cualquier lista del backend, eligiendo las columnas.
 *
 * No se configura por plugin: un botón «Exportar» se añade por JS a toda barra
 * de herramientas ligada a una lista, y los handlers se enganchan a
 * backend.ajax.beforeRunHandler. Se exporta lo que la lista ya muestra: la
 * misma consulta (filtros, búsqueda, orden y alcance por tenant del controller).
 */
class ListExporter
{
    const MAX_ROWS = 20000;
    const ASSET_VERSION = '4';

    public static function boot(): void
    {
        Event::listen('backend.page.beforeDisplay', function ($controller) {
            if (request()->ajax() || !BackendAuth::getUser()) {
                return;
            }
            $controller->addCss('/plugins/aero/sites/assets/css/list-export.css?v=' . self::ASSET_VERSION);
            $controller->addJs('/plugins/aero/sites/assets/js/list-export.js?v=' . self::ASSET_VERSION);
        });

        Event::listen('backend.ajax.beforeRunHandler', function ($controller, $handler) {
            if ($handler === 'onListExportLoad') {
                return self::popup($controller);
            }
            if ($handler === 'onListExportRun') {
                return self::run($controller);
            }
        });
    }

    /** Columnas que no tienen sentido en un archivo. */
    protected static function exportable($column): bool
    {
        return !in_array($column->type, ['image', 'file', 'colorpicker'], true);
    }

    protected static function findList($controller): Lists
    {
        $id = (string) post('list_id');
        foreach ($controller->widget as $widget) {
            if ($widget instanceof Lists && $widget->getId() === $id) {
                return $widget;
            }
        }
        throw new ApplicationException('No se encontró la lista a exportar.');
    }

    protected static function popup($controller): string
    {
        $list = self::findList($controller);
        $visible = array_keys($list->getVisibleColumns());

        $columns = [];
        foreach ($visible as $name) {
            $columns[$name] = ['column' => $list->getColumn($name), 'checked' => true];
        }
        foreach ($list->getColumns() as $name => $column) {
            $columns[$name] ??= ['column' => $column, 'checked' => false];
        }
        $columns = array_filter($columns, fn ($c) => self::exportable($c['column']));

        return self::render('popup', ['columns' => $columns, 'listId' => $list->getId()]);
    }

    protected static function run($controller)
    {
        $list = self::findList($controller);
        $format = post('format') === 'pdf' ? 'pdf' : 'csv';
        $delimiter = post('delimiter') === ',' ? ',' : ';';

        $all = $list->getColumns();
        $columns = [];
        foreach ((array) post('columns', []) as $name) {
            if (isset($all[$name]) && self::exportable($all[$name])) {
                $columns[$name] = $all[$name];
            }
        }
        if (!$columns) {
            throw new ApplicationException('Elige al menos una columna.');
        }

        $header = array_map(fn ($c) => trim(strip_tags(__($c->label))), array_values($columns));

        $rows = [];
        foreach ($list->prepareQuery()->limit(self::MAX_ROWS)->get() as $record) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = self::plain($list->getColumnValue($record, $column));
            }
            $rows[] = $row;
        }

        $title = trim(strip_tags((string) ($controller->pageTitle ?: 'Lista')));
        $slug = \Str::slug($title) ?: 'lista';
        $file = $slug . '-' . date('Ymd-His') . '.' . $format;

        $content = $format === 'pdf'
            ? self::pdf($title, $header, $rows)
            : self::csv($header, $rows, $delimiter);

        $token = \Str::random(40);
        $user = BackendAuth::getUser();
        $dir = storage_path('app/list-exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Se borran los archivos viejos al generar uno nuevo (nadie los descarga pasada una hora).
        foreach (glob($dir . '/*') ?: [] as $old) {
            if (is_file($old) && filemtime($old) < time() - 3600) {
                @unlink($old);
            }
        }
        file_put_contents("$dir/{$user->id}_{$token}", $content);
        file_put_contents("$dir/{$user->id}_{$token}.name", $file);

        return \Backend::redirect('aero/sites/listexport/download/' . $token);
    }

    public static function pathFor(string $token, int $userId): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }
        $path = storage_path("app/list-exports/{$userId}_{$token}");
        if (!is_file($path)) {
            return null;
        }
        return [$path, (string) @file_get_contents($path . '.name')];
    }

    /** Valor de celda en texto plano (los tipos de columna pueden devolver HTML). */
    protected static function plain($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value));
        }
        $value = preg_replace('#<(br|/p|/div|/li)\s*/?>#i', ' ', (string) $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    protected static function csv(array $header, array $rows, string $delimiter): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel lee bien los acentos
        $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; // evita fórmulas en Excel
        fputcsv($out, array_map($safe, $header), $delimiter);
        foreach ($rows as $row) {
            fputcsv($out, array_map($safe, $row), $delimiter);
        }
        rewind($out);

        return stream_get_contents($out);
    }

    protected static function pdf(string $title, array $header, array $rows): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new ApplicationException('El PDF no está disponible en este servidor.');
        }
        $html = self::render('pdf', [
            'title' => $title, 'header' => $header, 'rows' => $rows, 'date' => date('d/m/Y H:i'),
        ]);
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', count($header) > 5 ? 'landscape' : 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    protected static function render(string $view, array $vars): string
    {
        extract($vars);
        ob_start();
        include __DIR__ . "/listexporter/{$view}.php";

        return (string) ob_get_clean();
    }
}
