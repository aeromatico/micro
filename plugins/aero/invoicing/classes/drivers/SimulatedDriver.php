<?php namespace Aero\Invoicing\Classes\Drivers;

use Aero\Invoicing\Models\Cufd;
use Aero\Invoicing\Models\Invoice;
use Aero\Invoicing\Models\Issuer;
use Illuminate\Support\Str;

/**
 * No habla con ningún SIN: sirve para probar el flujo completo sin NIT ni
 * certificado. Las facturas que genera NO tienen validez tributaria.
 * Regla de prueba: un comprador con NIT 99999999 es rechazado.
 */
class SimulatedDriver implements InvoicingDriver
{
    public const REJECTED_NIT = '99999999';

    public function code(): string
    {
        return 'simulated';
    }

    public function obtainCufd(Issuer $issuer): array
    {
        return [
            'cuis'         => strtoupper(Str::random(8)),
            'cufd'         => strtoupper(Str::random(40)),
            'control_code' => strtoupper(Str::random(4)),
            'address'      => $issuer->address,
            'valid_until'  => now()->addHours(24),
        ];
    }

    public function emit(Issuer $issuer, Invoice $invoice, Cufd $cufd): array
    {
        if ($invoice->buyer_nit === self::REJECTED_NIT) {
            return [
                'cuf' => null, 'number' => null, 'status' => 'rejected', 'reception_code' => null, 'qr_url' => null,
                'messages' => ['Simulado: NIT del comprador inválido.'], 'xml' => null,
            ];
        }

        $number = (string) (((int) Invoice::where('issuer_id', $issuer->id)->max('number')) + 1);
        $cuf = strtoupper(substr(hash('sha256', $issuer->nit . $invoice->uuid . $cufd->cufd), 0, 32));

        return [
            'cuf' => $cuf, 'number' => $number, 'status' => 'validated',
            'reception_code' => 'SIM-' . $number,
            'qr_url' => 'https://example.invalid/simulado?cuf=' . $cuf,
            'messages' => ['Simulado: sin validez tributaria.'],
            'xml' => "<facturaSimulada cuf=\"{$cuf}\"/>",
        ];
    }

    public function void(Issuer $issuer, Invoice $invoice, Cufd $cufd, int $reasonCode): array
    {
        return ['ok' => true, 'messages' => ['Simulado: anulada.']];
    }
}
