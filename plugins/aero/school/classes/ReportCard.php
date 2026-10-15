<?php namespace Aero\School\Classes;

use Aero\School\Models\Student;

/** Boletín de calificaciones en PDF (dompdf). Sin dompdf devuelve null y el llamador ofrece la versión HTML imprimible. */
class ReportCard
{
    public function html(Student $student): string
    {
        $data = app(GradeBook::class)->report($student);
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        ob_start();
        include __DIR__ . '/../views/reportcard.php';

        return (string) ob_get_clean();
    }

    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    /** Un solo PDF con el boletín de cada alumno (una página por alumno). */
    public function htmlMany(iterable $students): string
    {
        $head = '';
        $bodies = [];
        foreach ($students as $student) {
            try {
                $html = $this->html($student);
            } catch (SchoolException $e) {
                continue;
            }
            if (!$head && preg_match('~<head>.*</head>~s', $html, $m)) {
                $head = $m[0];
            }
            if (preg_match('~<body>(.*)</body>~s', $html, $m)) {
                $bodies[] = '<div style="page-break-after:always">' . $m[1] . '</div>';
            }
        }

        return '<!doctype html><html lang="es">' . $head . '<body>' . implode('', $bodies) . '</body></html>';
    }

    public function pdf(Student $student): ?string
    {
        return $this->render($this->html($student));
    }

    public function pdfMany(iterable $students): ?string
    {
        return $this->render($this->htmlMany($students));
    }

    protected function render(string $html): ?string
    {
        if (!self::available()) {
            return null;
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', storage_path('temp'));
        $options->set('fontCache', storage_path('temp'));

        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    public static function filename(Student $student): string
    {
        return 'boletin-' . \Illuminate\Support\Str::slug($student->full_name) . '.pdf';
    }
}
