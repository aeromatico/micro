<?php namespace Aero\Invoicing\Classes\Drivers;

use Aero\Invoicing\Models\Cufd;
use Aero\Invoicing\Models\Invoice;
use Aero\Invoicing\Models\Issuer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Llama a un workflow de Floogos (trigger Webhook) que ejecuta los nodos SIAT.
 *
 * CONTRATO PROVISIONAL: el workflow de Floogos aún no existe ni se probó
 * contra el piloto del SIN. Pedimos POST JSON a FLOOGOS_WEBHOOK_URL con
 *   { operation: obtener_cufd|emitir|anular, credential, ... }
 * firmado con HMAC-SHA256 del cuerpo en `X-Market-Signature`, y esperamos la
 * salida del nodo correspondiente (cuf, numeroFactura, estado, qrUrl, ...).
 * Los nombres de campo de salida son los de la documentación de Floogos.
 */
class FloogosDriver implements InvoicingDriver
{
    public function code(): string
    {
        return 'floogos';
    }

    public function obtainCufd(Issuer $issuer): array
    {
        $out = $this->call('obtener_cufd', $issuer, ['obtener_cuis' => true]);

        foreach (['cufd', 'codigoControl'] as $key) {
            if (blank($out[$key] ?? null)) {
                throw new DriverException("Floogos no devolvió {$key} al pedir la CUFD.");
            }
        }

        return [
            'cuis'         => $out['cuis'] ?? null,
            'cufd'         => $out['cufd'],
            'control_code' => $out['codigoControl'],
            'address'      => $out['direccion'] ?? null,
            'valid_until'  => !empty($out['fechaVigencia'])
                ? Carbon::parse($out['fechaVigencia'], 'America/La_Paz')
                : now()->addHours(23),
        ];
    }

    public function emit(Issuer $issuer, Invoice $invoice, Cufd $cufd): array
    {
        $out = $this->call('emitir', $issuer, [
            'cufd'           => $cufd->cufd,
            'codigo_control' => $cufd->control_code,
            'validar_estado' => true,
            'factura'        => [
                'comprador' => [
                    'nit'           => $invoice->buyer_nit,
                    'razonSocial'   => $invoice->buyer_name,
                    'tipoDocumento' => (int) $invoice->buyer_doc_type,
                ],
                'items' => collect($invoice->items)->map(fn ($i) => [
                    'descripcion'       => $i['description'],
                    'cantidad'          => (float) $i['quantity'],
                    'precioUnitario'    => (float) $i['unit_price'],   // IVA 13 % incluido
                    'codigoProductoSin' => (string) $i['sin_product_code'],
                    'actividadEconomica' => (string) $i['economic_activity'],
                ])->all(),
            ],
        ]);

        $status = $out['estado'] ?? null;
        if (!in_array($status, ['validated', 'rejected', 'received'], true)) {
            throw new DriverException('Floogos devolvió un estado desconocido: ' . json_encode($status));
        }

        return [
            'cuf'            => $out['cuf'] ?? null,
            'number'         => isset($out['numeroFactura']) ? (string) $out['numeroFactura'] : null,
            'status'         => $status,
            'reception_code' => $out['codigoRecepcion'] ?? null,
            'qr_url'         => $out['qrUrl'] ?? null,
            'messages'       => (array) ($out['mensajes'] ?? []),
            'xml'            => $out['xmlFirmado'] ?? null,
        ];
    }

    public function void(Issuer $issuer, Invoice $invoice, Cufd $cufd, int $reasonCode): array
    {
        $out = $this->call('anular', $issuer, [
            'cufd'           => $cufd->cufd,
            'codigo_control' => $cufd->control_code,
            'cuf'            => $invoice->cuf,
            'motivo'         => $reasonCode,
        ]);

        return ['ok' => (bool) ($out['anulada'] ?? $out['ok'] ?? false), 'messages' => (array) ($out['mensajes'] ?? [])];
    }

    /** @return array<string, mixed> */
    protected function call(string $operation, Issuer $issuer, array $data): array
    {
        $url = config('aero.invoicing::floogos.url');
        $secret = config('aero.invoicing::floogos.secret');

        if (!$url || !$secret) {
            throw new DriverException('Floogos no está configurado (FLOOGOS_WEBHOOK_URL / FLOOGOS_WEBHOOK_SECRET).');
        }
        if (blank($issuer->provider_credential)) {
            throw new DriverException('El emisor no tiene credencial SIAT asignada en Floogos.');
        }

        $body = json_encode(['operation' => $operation, 'credential' => $issuer->provider_credential] + $data);

        try {
            $response = Http::timeout((int) config('aero.invoicing::floogos.timeout', 30))
                ->withOptions(['verify' => config('aero.invoicing::floogos.ca_bundle') ?: true])
                ->withHeaders(['X-Market-Signature' => hash_hmac('sha256', $body, $secret)])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $e) {
            throw new DriverException('No se pudo conectar con Floogos: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful()) {
            throw new DriverException("Floogos respondió HTTP {$response->status()}.");
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new DriverException('Floogos devolvió una respuesta que no es JSON.');
        }

        return $json;
    }
}
