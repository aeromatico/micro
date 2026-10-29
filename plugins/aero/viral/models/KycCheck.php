<?php namespace Aero\Viral\Models;

use Crypt;
use Event;
use Model;

/**
 * Verificación de identidad. Los documentos viven en un disco PRIVADO (nunca en
 * uploads/) y se borran en `purge_at`. El número de documento va cifrado.
 */
class KycCheck extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_viral_kyc_checks';

    const STATUSES = ['pending' => 'Pendiente', 'verified' => 'Verificada', 'rejected' => 'Rechazada', 'expired' => 'Vencida'];
    /** Días que se conservan los documentos tras decidir. */
    const RETAIN_DAYS = 30;

    public $fillable = ['application_id', 'user_id', 'driver', 'status', 'doc_front_path', 'doc_back_path', 'selfie_path', 'external_ref'];
    public $hidden = ['doc_number_enc'];

    public $rules = [
        'application_id' => 'required|integer',
        'user_id'        => 'required|integer',
        'driver'         => 'required|alpha_dash',
    ];

    public $belongsTo = [
        'application' => [Application::class, 'key' => 'application_id'],
    ];

    public $dates = ['reviewed_at', 'purge_at'];

    public function setDocNumberAttribute($value): void
    {
        $this->attributes['doc_number_enc'] = $value !== null && $value !== '' ? Crypt::encryptString((string) $value) : null;
    }

    public function getDocNumberAttribute(): ?string
    {
        try {
            return $this->doc_number_enc ? Crypt::decryptString($this->doc_number_enc) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function verify(?int $reviewerId = null): void
    {
        $this->decide('verified', $reviewerId, null);
        $this->application?->forceFill(['kyc_status' => 'verified'])->save();
        Event::fire('aero.viral.kycVerified', [$this]);
    }

    public function reject(?int $reviewerId, string $reason): void
    {
        $this->decide('rejected', $reviewerId, $reason);
        $this->application?->forceFill(['kyc_status' => 'rejected'])->save();
        Event::fire('aero.viral.kycRejected', [$this]);
    }

    protected function decide(string $status, ?int $reviewerId, ?string $reason): void
    {
        $this->status = $status;
        $this->reviewer_id = $reviewerId;
        $this->reviewed_at = now();
        $this->reject_reason = $reason;
        $this->purge_at = now()->addDays(self::RETAIN_DAYS);
        $this->save();
    }
}
