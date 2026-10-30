<?php namespace Aero\Viral\Models;

use Aero\Viral\Classes\Catalog;
use Event;
use Illuminate\Support\Str;
use Model;

class Application extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_viral_applications';

    const TRACKS = ['shorts' => 'Shorts', 'live' => 'Live', 'edit' => 'Edición'];
    const STATUSES = [
        'received' => 'Recibida', 'in_review' => 'En revisión', 'changes_requested' => 'Con cambios pedidos',
        'approved' => 'Aprobada', 'rejected' => 'Rechazada',
    ];
    const LEVELS = [1 => 'Noob', 2 => 'Casual', 3 => 'Pro', 4 => 'Viral'];
    /** Feedbacks positivos para subir desde el nivel indicado. Pro y Viral no suben solos: Viral lo decide el equipo. */
    const PROMOTE_AFTER = [1 => 3, 2 => 10];
    const KYC = ['none' => 'Sin verificar', 'pending' => 'Pendiente', 'verified' => 'Verificada', 'rejected' => 'Rechazada'];

    public $fillable = ['user_id', 'track', 'level', 'rate_model', 'rate_amount', 'positive_feedbacks', 'tags', 'description', 'phone', 'department', 'city',
        'offer_to_clients', 'terms_accepted_at', 'adult_confirmed_at'];

    public $jsonable = ['tags'];

    public $rules = [
        'user_id' => 'required|integer',
        'track'   => 'required|in:shorts,live,edit',
        'level'   => 'required|integer|between:1,4',
        'rate_amount' => 'nullable|numeric|min:0',
        'description' => 'nullable|max:600',
    ];

    public $belongsTo = [
        'user' => [\RainLab\User\Models\User::class, 'key' => 'user_id'],
    ];

    public $hasMany = [
        'samples'    => [Sample::class, 'key' => 'application_id'],
        'kyc_checks' => [KycCheck::class, 'key' => 'application_id'],
    ];

    public $dates = ['terms_accepted_at', 'adult_confirmed_at', 'reviewed_at'];

    public function beforeCreate(): void
    {
        $this->uuid = $this->uuid ?: (string) Str::uuid();
    }

    public function getTrackLabelAttribute(): string { return self::TRACKS[$this->track] ?? (string) $this->track; }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? (string) $this->status; }
    public function getLevelLabelAttribute(): string { return self::LEVELS[$this->level] ?? (string) $this->level; }
    public function getRateLabelAttribute(): string { return Catalog::rateLabel($this->rate_model, $this->rate_amount, (string) $this->track, (int) $this->level); }
    public function getKycLabelAttribute(): string { return self::KYC[$this->kyc_status] ?? (string) $this->kyc_status; }

    public function getRateModelOptions(): array
    {
        return ['per_second' => 'Por segundo', 'per_hour' => 'Por hora', 'commission_fixed' => 'Comisión fija por venta',
            'commission_percent' => 'Comisión % por venta', 'per_project' => 'Por proyecto'];
    }

    /**
     * Suma un feedback positivo. Al llegar al umbral sube un nivel (Noob→Casual con 3, Casual→Pro con 10)
     * y el contador vuelve a 0. Devuelve true si cambió de nivel.
     */
    public function addPositiveFeedback(): bool
    {
        return \DB::transaction(function () {
            $row = static::lockForUpdate()->find($this->id);
            $row->positive_feedbacks++;
            $from = (int) $row->level;
            $need = self::PROMOTE_AFTER[$from] ?? null;
            $promoted = $need !== null && $row->positive_feedbacks >= $need;

            if ($promoted) {
                $row->level = $from + 1;
                $row->positive_feedbacks = 0;
                $row->level_changed_at = now();
                if ($row->track === 'shorts' && $row->level === 2) {
                    $row->rate_amount = null; // En Casual la tarifa (Bs 3 a 10) la fija la revisión
                }
            }
            $row->forceSave();
            $this->setRawAttributes($row->getAttributes(), true);

            if ($promoted) {
                Event::fire('aero.viral.levelChanged', [$this, $from, (int) $this->level]);
            }

            return $promoted;
        });
    }

    public function getTrackOptions(): array { return self::TRACKS; }
    public function getStatusOptions(): array { return self::STATUSES; }
    public function getKycStatusOptions(): array { return self::KYC; }

    // --- Revisión -----------------------------------------------------------

    public function markInReview(?int $reviewerId = null): void
    {
        $this->transition('in_review', $reviewerId);
    }

    /** Solo se aprueba con identidad verificada. */
    public function approve(?int $reviewerId = null, ?string $notes = null): void
    {
        if ($this->kyc_status !== 'verified') {
            throw new \RuntimeException('No se puede aprobar sin verificación de identidad.');
        }
        $this->transition('approved', $reviewerId, $notes, 'applicationApproved');
    }

    public function reject(?int $reviewerId = null, ?string $notes = null): void
    {
        $this->transition('rejected', $reviewerId, $notes, 'applicationRejected');
    }

    public function requestChanges(?int $reviewerId, string $notes): void
    {
        $this->transition('changes_requested', $reviewerId, $notes, 'applicationChangesRequested');
    }

    protected function transition(string $status, ?int $reviewerId, ?string $notes = null, ?string $event = null): void
    {
        $this->status = $status;
        $this->reviewer_id = $reviewerId;
        $this->reviewed_at = now();
        if ($notes !== null) {
            $this->review_notes = $notes;
        }
        $this->save();

        if ($event) {
            Event::fire('aero.viral.' . $event, [$this]);
        }
    }
}
