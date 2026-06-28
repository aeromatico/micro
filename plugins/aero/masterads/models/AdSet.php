<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * AdSet — Meta Ads ad set (mid-tier between Campaign and Ad).
 *
 * Holds targeting (audience definition) and bidding/optimization configuration.
 * Belongs to a Campaign and aggregates many Ads. The `meta_id` is the external
 * identifier returned by Meta and is the natural key used by
 * {@see self::upsertByMetaId()} for idempotent sync (P4).
 *
 * @property int          $id
 * @property int          $campaign_id
 * @property string       $meta_id
 * @property string       $name
 * @property string       $status       ACTIVE|PAUSED|ARCHIVED|DELETED
 * @property array|null   $targeting    JSON targeting spec from Meta
 * @property float|null   $daily_budget
 * @property string|null  $optimization_goal
 * @property string|null  $bid_strategy
 */
class AdSet extends Model
{
    // Tenant-scoped indirectly via parent relation (no direct workspace_id column).
    // Access is filtered through Campaign → MetaAccount → Workspace; see BelongsToTenantScope.
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_masterads_ad_sets';

    public $fillable = [
        'campaign_id',
        'meta_id',
        'name',
        'status',
        'targeting',
        'daily_budget',
        'optimization_goal',
        'bid_strategy',
    ];

    public $jsonable = ['targeting'];

    public $rules = [
        'campaign_id' => 'required|exists:aero_masterads_campaigns,id',
        'meta_id'     => 'required|string|unique:aero_masterads_ad_sets,meta_id',
        'name'        => 'required|max:255',
        'status'      => 'required|in:ACTIVE,PAUSED,ARCHIVED,DELETED',
    ];

    public $belongsTo = [
        'campaign' => [Campaign::class],
    ];

    public $hasMany = [
        'ads' => [Ad::class],
    ];

    /**
     * Idempotent upsert keyed by the Meta-side identifier.
     *
     * @param  array $metaPayload    Raw payload from /campaigns/{id}/adsets
     * @param  int   $campaignId     FK to aero_masterads_campaigns.id
     */
    public static function upsertByMetaId(array $metaPayload, int $campaignId): self
    {
        $attributes = [
            'campaign_id' => $campaignId,
        ];

        foreach (['name', 'status', 'optimization_goal', 'bid_strategy'] as $field) {
            if (array_key_exists($field, $metaPayload)) {
                $attributes[$field] = $metaPayload[$field];
            }
        }

        // Targeting is a JSON column — accept array or already-decoded JSON.
        if (array_key_exists('targeting', $metaPayload) && $metaPayload['targeting'] !== null) {
            $targeting = $metaPayload['targeting'];
            if (is_string($targeting)) {
                $decoded = json_decode($targeting, true);
                $targeting = is_array($decoded) ? $decoded : [];
            }
            $attributes['targeting'] = $targeting;
        }

        // Meta delivers `daily_budget` in minor units (cents). Persist as decimal.
        if (array_key_exists('daily_budget', $metaPayload) && $metaPayload['daily_budget'] !== null) {
            $attributes['daily_budget'] = ((int) $metaPayload['daily_budget']) / 100;
        }

        return static::updateOrCreate(
            ['meta_id' => $metaPayload['id']],
            $attributes
        );
    }

    public function getStatusOptions(): array
    {
        return [
            'ACTIVE'   => 'ACTIVE',
            'PAUSED'   => 'PAUSED',
            'ARCHIVED' => 'ARCHIVED',
            'DELETED'  => 'DELETED',
        ];
    }

    public function getCampaignIdOptions(): array
    {
        return Campaign::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
