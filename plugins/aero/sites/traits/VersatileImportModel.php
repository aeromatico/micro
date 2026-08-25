<?php namespace Aero\Sites\Traits;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;

/**
 * VersatileImportModel se mezcla en subclases de Backend\Models\ImportModel
 * para soportar, además de CSV/JSON (que ya trae October de fábrica),
 * archivos de MS Excel (.xlsx/.xls).
 *
 * El truco: en vez de reimplementar el parser de columnas/preview/import
 * para un formato nuevo, convertimos el .xlsx a un CSV temporal la primera
 * vez que se pide la ruta del archivo, y dejamos que todo el pipeline
 * nativo de October (getImportFileColumnsFromCsv, processImportDataAsCsv,
 * etc.) trabaje como si siempre hubiera sido un CSV normal.
 *
 * Requiere que el campo `import_file` acepte xlsx/xls (ver
 * Aero\Sites\Behaviors\VersatileImportExportController::extendImportUploadFields).
 */
trait VersatileImportModel
{
    /**
     * getImportFilePath intercepta la ruta del archivo subido y, si es un
     * Excel, lo convierte a CSV (cacheado por ruta) antes de devolverla.
     */
    public function getImportFilePath($sessionKey = null)
    {
        $path = parent::getImportFilePath($sessionKey);

        if (!$path) {
            return $path;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'xlsm', 'ods'], true)) {
            return $path;
        }

        return $this->convertSpreadsheetToCsv($path);
    }

    protected function convertSpreadsheetToCsv(string $sourcePath): string
    {
        $csvPath = storage_path('app/uploads/import-cache-' . md5($sourcePath) . '.csv');

        if (!is_file($csvPath) || filemtime($csvPath) < filemtime($sourcePath)) {
            $spreadsheet = IOFactory::load($sourcePath);

            $writer = new CsvWriter($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setSheetIndex(0);
            $writer->save($csvPath);
        }

        return $csvPath;
    }
}
