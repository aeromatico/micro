<?php namespace Aero\Viral\Models;

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
    const LEVELS = [1 => 'Rookie', 2 => 'En racha', 3 => 'Pro', 4 => 'Leyenda'];
    const KYC = ['none' => 'Sin verificar', 'pending' => 'Pendiente', 'verified' => 'Verificada', 'rejected' => 'Rechazada'];

    public $fillable = ['user_id', 'track', 'level', 'tags', 'description', 'phone', 'department', 'city',
        'offer_to_clients', 'terms_accepted_at', 'adult_confirmed_at'];

    public $jsonable = ['tags'];

    public $rules = [
        'user_id' => 'required|integer',
        'track'   => 'required|in:shorts,live,edit',
        'level'   => 'required|integer|between:1,4',
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
    public function getKycLabelAttribute(): string { return self::KYC[$this->kyc_status] ?? (string) $this->kyc_status; }

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
