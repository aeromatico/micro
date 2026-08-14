<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * Insight — daily time-series metric record for a Meta entity.
 *
 * One row represents the daily delivery numbers (impressions, clicks, spend,
 * conversions, video_views) for a single entity in the Meta hierarchy
 * (Campaign | AdSet | Ad), identified by the polymorphic pair
 * (`entity_type`, `entity_id`) and bucketed by `date`.
 *
 * The natural key (`entity_type`, `entity_id`, `date`) is enforced by a
 * UNIQUE index in `aero_masterads_insights` and is the basis of the
 * idempotent upsert in {@see self::upsertByEntityDate()}, which guarantees
 * Property P4 (Sync Idempotency): re-running `SyncInsightsJob` over the
 * same window never produces duplicate rows.
 *
 * Timestamps:
 *  - `$timestamps = false`: there is no `updated_at` column. Daily metrics
 *    are append-only / overwritten in place; modification history is not
 *    tracked.
 *  - `CREATED_AT = 'created_at'` is kept for the ingest moment (set
 *    explicitly by callers or via DB default).
 *
 * @property int                 $id
 * @property string              $entity_type   campaign|adset|ad
 * @property int                 $entity_id
 * @property \Carbon\Carbon      $date
 * @property int                 $impressions
 * @property int                 $clicks
 * @property string              $spend         decimal(12,4) as string
 * @property int                 $conversions
 * @property int|null            $video_views
 * @property \Carbon\Carbon|null $created_at
 */
class Insight extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_masterads_insights';

    /**
     * The migration only defines `created_at`; there is no `updated_at`.
     * Disable Eloquent's automatic timestamp management entirely.
     */
    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public $fillable = [
        'entity_type',
        'entity_id',
        'date',
        'impressions',
        'clicks',
        'spend',
        'conversions',
        'video_views',
    ];

    protected $dates = ['date', 'created_at'];

    protected $casts = [
        'impressions' => 'integer',
        'clicks'      => 'integer',
        'spend'       => 'decimal:4',
        'conversions' => 'integer',
        'video_views' => 'integer',
    ];

    public $rules = [
        'entity_type' => 'required|in:campaign,adset,ad',
        'entity_id'   => 'required|integer',
        'date'        => 'required|date',
        'impressions' => 'required|integer|min:0',
        'clicks'      => 'required|integer|min:0',
        'spend'       => 'required|numeric|min:0',
        'conversions' => 'required|integer|min:0',
    ];

    /**
     * Limit the query to records dated within the last N days (inclusive).
     *
     * Used by `MetricsAggregator` to retrieve the lookback window that
     * feeds the AI analysis prompt.
     */
    public function scopeLookback($query, int $days)
    {
        return $query->where('date', '>=', now()->subDays($days)->toDateString());
    }

    /**
     * Limit the query to insights belonging to a single Meta entity.
     */
    public function scopeForEntity($query, string $type, int $id)
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    /**
     * Idempotent upsert keyed by the natural tuple (entity_type, entity_id, date).
     *
     * Mirrors the UNIQUE index defined in `create_insights_table` and is the
     * canonical entry point used by `SyncInsightsJob` to ingest the daily
     * pages returned by `/insights`. Re-invoking this method with the same
     * key updates the existing row instead of inserting a duplicate, which
     * is what realises Property P4 (Sync Idempotency).
     *
     * @param  array  $data  Must contain at least `entity_type`, `entity_id`
     *                       and `date`; remaining keys are treated as
     *                       updatable metric columns.
     */
    public static function upsertByEntityDate(array $data): self
    {
        return static::updateOrCreate(
            [
                'entity_type' => $data['entity_type'],
                'entity_id'   => $data['entity_id'],
                'date'        => $data['date'],
            ],
            $data
        );
    }

    public function getEntityTypeOptions(): array
    {
        return [
            'campaign' => 'campaign',
            'adset'    => 'adset',
            'ad'       => 'ad',
        ];
    }
}
