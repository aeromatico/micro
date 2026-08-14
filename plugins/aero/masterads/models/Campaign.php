<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * Campaign — Meta Ads campaign (highest entity in the Meta hierarchy).
 *
 * Belongs to a MetaAccount and aggregates many AdSets. The `meta_id` is the
 * external identifier returned by the Meta Graph API and is the natural key
 * used by {@see self::upsertByMetaId()} to guarantee idempotency on sync (P4).
 *
 * Budget fields (`daily_budget`, `lifetime_budget`) are stored in the
 * workspace's currency (decimal), translated from Meta's minor-units (cents)
 * payload by dividing by 100.
 *
 * @property int         $id
 * @property int         $meta_account_id
 * @property string      $meta_id
 * @property string      $name
 * @property string|null $objective
 * @property string      $status        ACTIVE|PAUSED|ARCHIVED|DELETED
 * @property float|null  $daily_budget
 * @property float|null  $lifetime_budget
 * @property \Carbon\Carbon|null $start_time
 * @property \Carbon\Carbon|null $stop_time
 */
class Campaign extends Model
{
    // Tenant-scoped indirectly via parent relation (no direct workspace_id column).
    // Access is filtered through MetaAccount → Workspace; see BelongsToTenantScope.
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_masterads_campaigns';

    public $fillable = [
        'meta_account_id',
        'meta_id',
        'name',
        'objective',
        'status',
        'daily_budget',
        'lifetime_budget',
        'start_time',
        'stop_time',
    ];

    protected $dates = ['start_time', 'stop_time'];

    public $rules = [
        'meta_account_id' => 'required|exists:aero_masterads_meta_accounts,id',
        'meta_id'         => 'required|string|unique:aero_masterads_campaigns,meta_id',
        'name'            => 'required|max:255',
        'status'          => 'required|in:ACTIVE,PAUSED,ARCHIVED,DELETED',
    ];

    public $belongsTo = [
        'meta_account' => [MetaAccount::class],
    ];

    public $hasMany = [
        'ad_sets' => [AdSet::class],
    ];

    /**
     * Idempotent upsert keyed by the Meta-side identifier.
     *
     * Translates the Meta Graph API payload field names into the local
     * schema (notably `id` → `meta_id`) and converts budgets from minor
     * units (cents) to the configured decimal column. Re-running this
     * method with the same `$metaPayload['id']` updates the existing row
     * instead of creating a duplicate — this is what guarantees
     * sync-idempotency property P4.
     *
     * @param  array  $metaPayload     Raw payload from /act_{id}/campaigns
     * @param  int    $metaAccountId   FK to aero_masterads_meta_accounts.id
     */
    public static function upsertByMetaId(array $metaPayload, int $metaAccountId): self
    {
        $attributes = [
            'meta_account_id' => $metaAccountId,
        ];

        // 1:1 field translation (Meta field name === local column name).
        foreach (['name', 'objective', 'status'] as $field) {
            if (array_key_exists($field, $metaPayload)) {
                $attributes[$field] = $metaPayload[$field];
            }
        }

        // Budgets in Meta come as integer strings in the account's minor
        // currency unit (cents). Convert to decimal in workspace currency.
        foreach (['daily_budget', 'lifetime_budget'] as $budgetField) {
            if (array_key_exists($budgetField, $metaPayload) && $metaPayload[$budgetField] !== null) {
                $attributes[$budgetField] = ((int) $metaPayload[$budgetField]) / 100;
            }
        }

        // Date fields: pass-through (Eloquent's $dates cast will parse).
        foreach (['start_time', 'stop_time'] as $dateField) {
            if (array_key_exists($dateField, $metaPayload) && $metaPayload[$dateField] !== null) {
                $attributes[$dateField] = $metaPayload[$dateField];
            }
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

    public function getMetaAccountIdOptions(): array
    {
        return MetaAccount::orderBy('meta_act_id')
            ->pluck('meta_act_id', 'id')
            ->toArray();
    }
}
