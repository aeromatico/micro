<?php namespace Aero\Invoicing\Classes\Drivers;

use Aero\Invoicing\Models\Cufd;
use Aero\Invoicing\Models\Invoice;
use Aero\Invoicing\Models\Issuer;

/**
 * Quien habla con el SIAT. Hoy: Floogos (por detrás) y un simulado. Mañana
 * podría ser SIAT directo sin tocar el resto del plugin.
 */
interface InvoicingDriver
{
    public function code(): string;

    /**
     * @return array{cuis: ?string, cufd: string, control_code: string, address: ?string, valid_until: \Carbon\Carbon}
     * @throws DriverException
     */
    public function obtainCufd(Issuer $issuer): array;

    /**
     * @return array{cuf: ?string, number: ?string, status: string, reception_code: ?string, qr_url: ?string, messages: array, xml: ?string}
     *         status: validated | rejected | received
     * @throws DriverException si no hay respuesta fiable (reintentable)
     */
    public function emit(Issuer $issuer, Invoice $invoice, Cufd $cufd): array;

    /**
     * @return array{ok: bool, messages: array}
     * @throws DriverException
     */
    public function void(Issuer $issuer, Invoice $invoice, Cufd $cufd, int $reasonCode): array;
}
