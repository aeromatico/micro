<?php namespace Aero\Sites\Behaviors;

use ApplicationException;
use Backend\Behaviors\ImportExportController;
use League\Csv\Writer as CsvWriter;
use System\Models\File as SystemFile;

/**
 * VersatileImportExportController extiende el comportamiento nativo de
 * October `Backend\Behaviors\ImportExportController` para que cualquier
 * controller del proyecto pueda ofrecer, sin repetir código:
 *
 *   1. CSV con delimitador/enclosure/escape/encoding configurables
 *      (esto ya lo trae October de fábrica con file_format=csv_custom).
 *   2. MS Excel (.xlsx/.xls) — el modelo de import debe usar el trait
 *      Aero\Sites\Traits\VersatileImportModel, que convierte el archivo
 *      a CSV de forma transparente.
 *   3. Copiar y Pegar — un textarea donde el usuario pega filas (de Excel,
 *      Sheets, o separadas por comas) y las convertimos a un CSV temporal
 *      que se adjunta como si fuera un archivo subido.
 *
 * Uso en un controller:
 *
 *     public $implement = [
 *         \Aero\Sites\Behaviors\VersatileImportExportController::class,
 *         \Backend\Behaviors\ListController::class,
 *         \Backend\Behaviors\FormController::class,
 *     ];
 *     public $importExportConfig = 'config_import_export.yaml';
 *
 * Ver docs/workflows.md — Flujo "Importación versátil (CSV/Excel/Pegar)"
 * para la guía completa de cómo sumar import a otro modelo.
 */
class VersatileImportExportController extends ImportExportController
{
    protected $actions = ['import', 'export', 'download'];

    public function __construct($controller)
    {
        parent::__construct($controller);

        // Nuestra clase vive en otra carpeta que la del núcleo, así que
        // sumamos los partials nativos (container_import, import_form,
        // column_sample_form, etc.) como ruta de búsqueda adicional.
        $this->addViewPath(base_path('modules/backend/behaviors/importexportcontroller/partials'), true);
    }

    /**
     * makeImportUploadFormWidget añade xlsx/xls a los tipos de archivo
     * aceptados y agrega la sección "Copiar y Pegar" debajo del uploader.
     */
    protected function makeImportUploadFormWidget()
    {
        $widget = parent::makeImportUploadFormWidget();

        if (!$widget) {
            return $widget;
        }

        if ($fileField = $widget->getField('import_file')) {
            $fileField->fileTypes = array_unique(array_merge(
                (array) ($fileField->fileTypes ?? []),
                ['csv', 'json', 'xlsx', 'xls']
            ));
        }

        $widget->addFields([
            'paste_section' => [
                'label' => '¿No tienes un archivo? Copiar y Pegar',
                'type'  => 'section',
            ],
            'paste_area' => [
                'type' => 'partial',
                'path' => '$/aero/sites/behaviors/versatileimportexportcontroller/partials/_import_paste.php',
                'span' => 'full',
            ],
        ], 'primary');

        return $widget;
    }

    /**
     * onImportFromPaste convierte el texto pegado por el usuario en un CSV
     * temporal y lo adjunta como `import_file` (igual que haría el widget de
     * subida de archivos), luego re-renderiza el behavior completo para que
     * aparezca el paso 2 (emparejar columnas), como si el usuario hubiera
     * subido un archivo real.
     */
    public function onImportFromPaste()
    {
        $pasteData = trim((string) post('paste_data'));

        if ($pasteData === '') {
            throw new ApplicationException('Pega algunas filas de datos antes de continuar.');
        }

        $delimiter = post('paste_delimiter') === 'comma' ? ',' : "\t";

        $rows = preg_split('/\r\n|\r|\n/', $pasteData);
        $rows = array_values(array_filter($rows, fn ($row) => trim($row) !== ''));

        if (!$rows) {
            throw new ApplicationException('No se detectaron filas válidas en el texto pegado.');
        }

        $csv = CsvWriter::fromString('');
        $csv->setDelimiter(',');
        $csv->setEnclosure('"');

        foreach ($rows as $row) {
            $csv->insertOne(str_getcsv($row, $delimiter, '"', '\\'));
        }

        $model      = $this->importGetModel();
        $sessionKey = $this->importUploadFormWidget->getSessionKey();

        $file = new SystemFile;
        $file->is_public = false;
        $file->fromData($csv->toString(), 'pegado-' . now()->format('Ymd-His') . '.csv');
        $file->save();

        $model->import_file()->add($file, $sessionKey);

        // importRender() usa $this->vars (importUploadFormWidget, importDbColumns,
        // etc.), que el núcleo solo llena en el GET inicial de la página import()
        // vía prepareImportVars(). Como esto corre en un handler AJAX, hay que
        // volver a poblarlas explícitamente antes de re-renderizar.
        $this->prepareImportVars();

        return $this->importRender();
    }
}
