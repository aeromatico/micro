<?php namespace Aero\Invoicing\Classes;

use Aero\Invoicing\Models\Invoice;
use Illuminate\Support\Str;

/**
 * Representación gráfica de la factura en PDF (dompdf, sin plugins de pago).
 * Una factura que no está válida o recibida sale con marca «Sin valor fiscal».
 */
class InvoicePdf
{
    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public function html(Invoice $invoice): string
    {
        $issuer = $invoice->issuer;
        $items = (array) $invoice->items;
        $fiscal = in_array($invoice->status, ['validated', 'received', 'voided'], true);
        $qr = $this->qrDataUri($invoice->qr_url);
        $words = self::amountInWords((float) $invoice->total);
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $money = fn ($v) => number_format((float) $v, 2, '.', ',');

        ob_start();
        include __DIR__ . '/../views/invoice.php';

        return (string) ob_get_clean();
    }

    public function pdf(Invoice $invoice): ?string
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
        $pdf->loadHtml($this->html($invoice), 'UTF-8');
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    public static function filename(Invoice $invoice): string
    {
        return 'factura-' . Str::slug($invoice->number ?: $invoice->uuid) . '.pdf';
    }

    /** QR del SIN como imagen incrustada; sin URL o sin librería, no se dibuja. */
    protected function qrDataUri(?string $url): ?string
    {
        if (!$url || !class_exists(\BaconQrCode\Writer::class)) {
            return null;
        }

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(180, 0),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );

        return 'data:image/svg+xml;base64,' . base64_encode((new \BaconQrCode\Writer($renderer))->writeString($url));
    }

    /** «Son: ciento trece 00/100 bolivianos», como exige la representación gráfica. */
    public static function amountInWords(float $amount): string
    {
        $int = (int) floor($amount);
        $cents = (int) round(($amount - $int) * 100);

        return 'Son: ' . self::words($int) . sprintf(' %02d/100 bolivianos', $cents);
    }

    /** «uno» → «un» y «veintiuno» → «veintiún» delante de mil/millones. */
    protected static function apocope(string $w): string
    {
        return preg_replace(['/veintiuno$/', '/uno$/'], ['veintiún', 'un'], $w);
    }

    protected static function words(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        $u = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
            'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
            'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        $d = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];
        $c = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos', 6 => 'seiscientos',
            7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];

        if ($n < 30) {
            return $u[$n];
        }
        if ($n < 100) {
            return $d[intdiv($n, 10)] . ($n % 10 ? ' y ' . $u[$n % 10] : '');
        }
        if ($n === 100) {
            return 'cien';
        }
        if ($n < 1000) {
            return $c[intdiv($n, 100)] . ($n % 100 ? ' ' . self::words($n % 100) : '');
        }
        if ($n < 1000000) {
            $k = intdiv($n, 1000);
            $head = $k === 1 ? 'mil' : self::apocope(self::words($k)) . ' mil';

            return $head . ($n % 1000 ? ' ' . self::words($n % 1000) : '');
        }
        $m = intdiv($n, 1000000);
        $head = $m === 1 ? 'un millón' : self::apocope(self::words($m)) . ' millones';

        return $head . ($n % 1000000 ? ' ' . self::words($n % 1000000) : '');
    }
}
