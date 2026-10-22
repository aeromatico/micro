<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Agreement;

/** PDF del contrato (dompdf). Sin dompdf devuelve null y el panel ofrece la versión HTML imprimible. */
class AgreementPdf
{
    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    /** Marca de agua según el estado: borrador, sin revisión legal o ninguna (aprobado/firmado con plantilla revisada). */
    public static function watermark(Agreement $a): ?string
    {
        if (!$a->template_reviewed) {
            return 'BORRADOR — SIN REVISIÓN LEGAL';
        }

        return in_array($a->status, ['draft', 'in_review'], true) ? 'BORRADOR' : ($a->status === 'cancelled' ? 'CANCELADO' : null);
    }

    public function html(Agreement $a): string
    {
        $mark = self::watermark($a);
        $css = 'body{font-family:DejaVu Sans,sans-serif;font-size:11.5px;line-height:1.55;color:#111}h1{font-size:18px;margin:0 0 14px}h3{font-size:12.5px;margin:16px 0 4px}p{margin:0 0 7px;text-align:justify}'
            . '.wm{position:fixed;top:42%;left:0;right:0;text-align:center;font-size:44px;color:#d0d0d0;transform:rotate(-28deg);z-index:-1;font-weight:bold}'
            . '.foot{position:fixed;bottom:-18px;left:0;right:0;font-size:9px;color:#777;text-align:center}';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . e($a->code) . '</title><style>' . $css . '</style></head><body>'
            . ($mark ? '<div class="wm">' . e($mark) . '</div>' : '')
            . '<div class="foot">' . e($a->code) . ' · plantilla v' . (int) $a->template_version . ($mark ? ' · ' . e($mark) : '') . '</div>'
            . (string) $a->body . '</body></html>';
    }

    public function pdf(Agreement $a): ?string
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
        $pdf->loadHtml($this->html($a), 'UTF-8');
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    public static function filename(Agreement $a): string
    {
        return strtolower($a->code) . '-' . \Illuminate\Support\Str::slug($a->title) . '.pdf';
    }
}
