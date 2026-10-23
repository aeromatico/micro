<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Contribution;

/** Comprobante de aporte en PDF (dompdf). Sin dompdf, el llamador ofrece el HTML imprimible. */
class ReceiptPdf
{
    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public function html(Contribution $c): string
    {
        $church = class_exists(\Aero\Sites\Models\Tenant::class) ? (\Aero\Sites\Models\Tenant::find($c->tenant_id)?->name ?? 'Iglesia') : 'Iglesia';
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $money = number_format((float) $c->amount, 2, ',', '.') . ' ' . $c->currency;
        $methods = \Aero\Church\Models\Contribution::METHODS;
        $doc = $c->member?->document;

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222}h1{font-size:18px;margin:0}'
            . '.box{border:1px solid #888;padding:18px;margin-top:10px}td{padding:5px 0;vertical-align:top}.k{width:35%;color:#555}'
            . '.big{font-size:20px;font-weight:bold}.foot{margin-top:40px;color:#666;font-size:10px}'
            . '</style></head><body><div class="box">'
            . '<h1>' . $e($church) . '</h1><p>Comprobante de aporte N.º <strong>' . $e($c->receipt_number) . '</strong></p>'
            . '<table width="100%">'
            . '<tr><td class="k">Fecha</td><td>' . $e($c->date->format('d/m/Y')) . '</td></tr>'
            . '<tr><td class="k">Recibido de</td><td>' . $e($c->donor_label) . ($doc ? ' (CI ' . $e($doc) . ')' : '') . '</td></tr>'
            . '<tr><td class="k">Concepto</td><td>' . $e($c->fund?->name) . '</td></tr>'
            . '<tr><td class="k">Forma de pago</td><td>' . $e($methods[$c->method] ?? $c->method) . '</td></tr>'
            . '<tr><td class="k">Monto</td><td class="big">' . $e($money) . '</td></tr>'
            . '</table><p class="foot">Gracias por su aporte. Este comprobante acredita la recepción del monto indicado.</p>'
            . '</div></body></html>';
    }

    public function pdf(Contribution $c): ?string
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
        $pdf->loadHtml($this->html($c), 'UTF-8');
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
