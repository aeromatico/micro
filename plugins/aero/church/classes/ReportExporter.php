<?php namespace Aero\Church\Classes;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Excel (.xlsx) y PDF de un reporte. Las celdas de texto se escriben como TEXTO: nada se interpreta como fórmula. */
class ReportExporter
{
    public const MAX_PDF_ROWS = 2000;

    public function filename(array $report, string $ext): string
    {
        return 'iglesia-' . \Illuminate\Support\Str::slug($report['title']) . '-' . now()->format('Ymd') . '.' . $ext;
    }

    public function xlsx(array $report): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $report['title']), 0, 31));

        $row = 1;
        $sheet->setCellValueExplicit('A1', $report['title'], DataType::TYPE_STRING);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $row = 3;
        foreach ($report['summary'] ?? [] as $k => $v) {
            $sheet->setCellValueExplicit("A{$row}", (string) $k, DataType::TYPE_STRING);
            $this->put($sheet, "B{$row}", $v);
            $row++;
        }
        $row++;

        $head = $row;
        foreach ($report['columns'] as $i => $col) {
            $sheet->setCellValueExplicit($this->col($i) . $head, (string) $col, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A' . $head . ':' . $this->col(count($report['columns']) - 1) . $head)->getFont()->setBold(true);

        foreach ($report['rows'] as $r) {
            $row++;
            foreach (array_values($r) as $i => $v) {
                $this->put($sheet, $this->col($i) . $row, $v);
            }
        }
        foreach (range(0, max(0, count($report['columns']) - 1)) as $i) {
            $sheet->getColumnDimension($this->col($i))->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'chx');
        (new Xlsx($book))->save($tmp);
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    protected function put($sheet, string $cell, $v): void
    {
        if (is_int($v) || is_float($v)) {
            $sheet->setCellValueExplicit($cell, $v, DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValueExplicit($cell, (string) ($v ?? ''), DataType::TYPE_STRING);
        }
    }

    protected function col(int $i): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    }

    public function html(array $report, string $church = ''): string
    {
        $e = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $rows = array_slice($report['rows'], 0, self::MAX_PDF_ROWS);

        $h = '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#222}h1{font-size:16px;margin:0}h2{font-size:11px;color:#555;margin:2px 0 10px}'
            . 'table{border-collapse:collapse;width:100%}th{background:#eee;text-align:left}th,td{border:1px solid #bbb;padding:3px 5px}'
            . '.sum td{border:none;padding:1px 6px 1px 0}</style></head><body>'
            . '<h1>' . $e($report['title']) . '</h1><h2>' . $e($church) . ' · ' . now()->format('d/m/Y H:i') . '</h2>';

        if (!empty($report['summary'])) {
            $h .= '<table class="sum">';
            foreach ($report['summary'] as $k => $v) {
                $h .= '<tr><td><strong>' . $e($k) . '</strong></td><td>' . $e($v) . '</td></tr>';
            }
            $h .= '</table><br>';
        }

        $h .= '<table><thead><tr>' . implode('', array_map(fn ($c) => '<th>' . $e($c) . '</th>', $report['columns'])) . '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr>' . implode('', array_map(fn ($v) => '<td>' . $e($v) . '</td>', array_values($r))) . '</tr>';
        }
        $h .= '</tbody></table>';
        if (count($report['rows']) > self::MAX_PDF_ROWS) {
            $h .= '<p>Se muestran las primeras ' . self::MAX_PDF_ROWS . ' filas; descarga el Excel para ver todas.</p>';
        }

        return $h . '</body></html>';
    }

    public function pdf(array $report, string $church = ''): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', storage_path('temp'));
        $options->set('fontCache', storage_path('temp'));

        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml($this->html($report, $church), 'UTF-8');
        $pdf->setPaper('letter', count($report['columns']) > 6 ? 'landscape' : 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
