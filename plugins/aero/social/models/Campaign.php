<?php namespace Aero\Social\Models;

use Model;

/** Campaña sincronizada desde la plataforma de anuncios. */
class Campaign extends Model
{
    public $table = 'aero_social_campaigns';

    protected $guarded = ['id'];

    protected $dates = ['start_time', 'stop_time', 'synced_at'];

    public $belongsTo = [
        'adAccount' => [AdAccount::class, 'key' => 'ad_account_id'],
    ];

    public $hasMany = [
        'adsets'   => [AdSet::class, 'key' => 'campaign_id'],
        'insights' => [Insight::class, 'key' => 'campaign_id'],
    ];

    public const STATUS_LABELS = [
        'ACTIVE' => 'Activa', 'PAUSED' => 'Pausada', 'DELETED' => 'Eliminada', 'ARCHIVED' => 'Archivada',
        'IN_PROCESS' => 'En revisión', 'WITH_ISSUES' => 'Con problemas', 'CAMPAIGN_PAUSED' => 'Pausada',
        'ADSET_PAUSED' => 'Conjunto pausado', 'PENDING_REVIEW' => 'En revisión', 'DISAPPROVED' => 'Rechazada',
    ];

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->effective_status ?: $this->status] ?? (string) ($this->effective_status ?: $this->status);
    }

    /** Dónde vive el presupuesto: la campaña (CBO) o su único conjunto. Null = no editable aquí. */
    public function budgetOwner(): Campaign|AdSet|null
    {
        if ($this->daily_budget || $this->lifetime_budget) {
            return $this;
        }

        $sets = $this->adsets()->where(fn ($q) => $q->whereNotNull('daily_budget')->orWhereNotNull('lifetime_budget'))->get();

        return $sets->count() === 1 ? $sets->first() : null;
    }
}
