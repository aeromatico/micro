<?php namespace Aero\Invoicing\Classes;

use Aero\Invoicing\Classes\Drivers\DriverException;
use Aero\Invoicing\Classes\Drivers\DriverFactory;
use Aero\Invoicing\Models\Invoice;
use Aero\Invoicing\Models\Issuer;
use Event;

class InvoiceService
{
    /**
     * Crea un borrador. Si llega `reference` y ya existe una factura con esa
     * referencia para el tenant, devuelve la existente (idempotencia).
     */
    public static function create(Issuer $issuer, array $data): Invoice
    {
        if (!empty($data['reference'])) {
            $existing = Invoice::where('tenant_id', $issuer->tenant_id)->where('reference', $data['reference'])->first();
            if ($existing) {
                return $existing;
            }
        }

        return Invoice::create([
            'tenant_id'      => $issuer->tenant_id,
            'issuer_id'      => $issuer->id,
            'reference'      => $data['reference'] ?? null,
            'buyer_nit'      => $data['buyer_nit'],
            'buyer_doc_type' => $data['buyer_doc_type'] ?? 5,
            'buyer_name'     => $data['buyer_name'],
            'buyer_email'    => $data['buyer_email'] ?? null,
            'items'          => array_values($data['items'] ?? []),
        ]);
    }

    /**
     * Envía la factura al SIAT. Solo `draft` y `error` pueden emitirse: una
     * factura ya emitida no se reenvía (duplicaría el documento fiscal).
     */
    public static function issue(Invoice $invoice): Invoice
    {
        if (!$invoice->isEditable) {
            throw new \DomainException("La factura {$invoice->uuid} está en estado «{$invoice->status}» y no puede emitirse.");
        }

        $issuer = $invoice->issuer;
        if (!$issuer || !$issuer->isReady) {
            throw new \DomainException('El emisor no está listo para facturar (inactivo o sin credencial del proveedor).');
        }

        // Reservar el envío: dos clics o dos workers no emiten dos veces.
        $claimed = Invoice::whereKey($invoice->id)->whereIn('status', ['draft', 'error'])->update(['status' => 'pending', 'error' => null]);
        if (!$claimed) {
            throw new \DomainException('La factura ya se está enviando.');
        }
        $invoice->status = 'pending';

        try {
            $cufd = CufdManager::current($issuer);
            $result = DriverFactory::for($issuer)->emit($issuer, $invoice, $cufd);
        } catch (DriverException $e) {
            // No hay respuesta fiable: no se sabe si el SIN la recibió. Queda
            // en error para reintento manual tras revisar en el proveedor.
            $invoice->status = 'error';
            $invoice->error = $e->getMessage();
            $invoice->forceSave();
            Event::fire('aero.invoicing.invoiceFailed', [$invoice]);

            return $invoice;
        }

        $invoice->status = $result['status'];
        $invoice->cuf = $result['cuf'];
        $invoice->number = $result['number'];
        $invoice->reception_code = $result['reception_code'];
        $invoice->qr_url = $result['qr_url'];
        $invoice->messages = $result['messages'];
        $invoice->xml = $result['xml'];
        $invoice->issued_at = $result['status'] === 'rejected' ? null : now();
        $invoice->forceSave();

        Event::fire($result['status'] === 'rejected' ? 'aero.invoicing.invoiceRejected' : 'aero.invoicing.invoiceIssued', [$invoice]);

        return $invoice;
    }

    /** Anula una factura emitida con un código de motivo del catálogo del SIN (1 = mal emitida). */
    public static function void(Invoice $invoice, int $reasonCode = 1): Invoice
    {
        if (!$invoice->isVoidable) {
            throw new \DomainException("Solo se anulan facturas emitidas (estado actual: {$invoice->status}).");
        }

        $issuer = $invoice->issuer;
        $result = DriverFactory::for($issuer)->void($issuer, $invoice, CufdManager::current($issuer), $reasonCode);

        $invoice->messages = $result['messages'];
        if (!$result['ok']) {
            $invoice->forceSave();
            throw new \DomainException('El SIN no aceptó la anulación: ' . implode(' ', $result['messages']));
        }

        $invoice->status = 'voided';
        $invoice->voided_at = now();
        $invoice->void_reason_code = $reasonCode;
        $invoice->forceSave();

        Event::fire('aero.invoicing.invoiceVoided', [$invoice]);

        return $invoice;
    }
}
