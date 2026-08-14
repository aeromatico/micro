<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * Ad — Meta Ads creative-level entity (leaf in the hierarchy).
 *
 * Belongs to an AdSet and carries the creative payload that Meta renders to
 * users. The `meta_id` is the external identifier returned by Meta and is the
 * natural key used by {@see self::upsertByMetaId()} for idempotent sync (P4).
 *
 * @property int         $id
 * @property int         $ad_set_id
 * @property string      $meta_id
 * @property string      $name
 * @property string      $status      ACTIVE|PAUSED|ARCHIVED|DELETED
 * @property array|null  $creative    JSON creative spec from Meta
 * @property string      $format      image|video|carousel|collection
 */
class Ad extends Model
{
    // Tenant-scoped indirectly via parent relation (no direct workspace_id column).
    // Access is filtered through AdSet → Campaign → MetaAccount → Workspace; see BelongsToTenantScope.
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_masterads_ads';

    public $fillable = [
        'ad_set_id',
        'meta_id',
        'name',
        'status',
        'creative',
        'format',
    ];

    public $jsonable = ['creative'];

    public $rules = [
        'ad_set_id' => 'required|exists:aero_masterads_ad_sets,id',
        'meta_id'   => 'required|string|unique:aero_masterads_ads,meta_id',
        'name'      => 'required|max:255',
        'status'    => 'required|in:ACTIVE,PAUSED,ARCHIVED,DELETED',
        'format'    => 'required|in:image,video,carousel,collection',
    ];

    public $belongsTo = [
        'ad_set' => [AdSet::class],
    ];

    /**
     * Idempotent upsert keyed by the Meta-side identifier.
     *
     * @param  array $metaPayload   Raw payload from /adsets/{id}/ads
     * @param  int   $adSetId       FK to aero_masterads_ad_sets.id
     */
    public static function upsertByMetaId(array $metaPayload, int $adSetId): self
    {
        $attributes = [
            'ad_set_id' => $adSetId,
        ];

        foreach (['name', 'status', 'format'] as $field) {
            if (array_key_exists($field, $metaPayload)) {
                $attributes[$field] = $metaPayload[$field];
            }
        }

        // Creative is a JSON column — accept array or already-decoded JSON.
        if (array_key_exists('creative', $metaPayload) && $metaPayload['creative'] !== null) {
            $creative = $metaPayload['creative'];
            if (is_string($creative)) {
                $decoded = json_decode($creative, true);
                $creative = is_array($decoded) ? $decoded : [];
            }
            $attributes['creative'] = $creative;
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

    public function getFormatOptions(): array
    {
        return [
            'image'      => 'image',
            'video'      => 'video',
            'carousel'   => 'carousel',
            'collection' => 'collection',
        ];
    }

    public function getAdSetIdOptions(): array
    {
        return AdSet::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
