<?php namespace Aero\Tickets\Models;

use Illuminate\Support\Facades\Crypt;
use Model;

/** Comprobante subido por el comprador, cifrado en la base. Se sirve solo por controlador con permiso. NO prueba el cobro. */
class PaymentProof extends Model
{
    use \Aero\Tickets\Classes\TenantScoped;

    public $table = 'aero_tickets_payment_proofs';
    public $guarded = [];
    protected $hidden = ['content'];

    public const MAX_BYTES = 3_145_728;
    public const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    public function beforeUpdate(): void
    {
        throw new \ApplicationException('Un comprobante no se modifica.');
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('Los comprobantes se conservan como evidencia.');
    }

    /** Valida el contenido real (no la extensión) y guarda cifrado. */
    public static function storeFor(Payment $payment, string $binary, ?string $name): self
    {
        if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw new \ApplicationException('El comprobante debe pesar menos de 3 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if (!isset(self::MIMES[$mime])) {
            throw new \ApplicationException('El comprobante debe ser una imagen (JPG, PNG, WEBP) o un PDF.');
        }

        return static::create([
            'tenant_id' => $payment->tenant_id, 'payment_id' => $payment->id, 'mime' => $mime,
            'name' => mb_substr(preg_replace('/[^\w.\- ]+/u', '_', (string) $name), 0, 120) ?: 'comprobante.' . self::MIMES[$mime],
            'size' => strlen($binary), 'sha256' => hash('sha256', $binary), 'content' => Crypt::encryptString(base64_encode($binary)),
        ]);
    }

    public function binary(): string
    {
        return base64_decode(Crypt::decryptString($this->content));
    }
}
