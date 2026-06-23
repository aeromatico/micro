<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * Plan — Catalog row that defines what a Subscription can do.
 *
 * A Plan represents a commercial tier (e.g. "free", "pro", "enterprise"). Its
 * caps (max Meta accounts, max analyses per month, whether auto-apply is
 * allowed) are read by `PlanLimiter` to enforce quotas across the plugin.
 *
 * Validates: Requirements 9.1, 9.2, 9.7, 17.1, 17.6
 *
 * @property int    $id
 * @property string $code
 * @property string $name
 * @property string $monthly_price
 * @property int    $max_meta_accounts
 * @property int    $max_analyses_month
 * @property bool   $auto_apply_allowed
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Plan extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table associated with the model
     */
    public $table = 'aero_masterads_plans';

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'code',
        'name',
        'monthly_price',
        'max_meta_accounts',
        'max_analyses_month',
        'auto_apply_allowed',
    ];

    /**
     * @var array rules — validation, per Requirement 9.1
     */
    public $rules = [
        'code'               => 'required|alpha_dash|unique:aero_masterads_plans,code',
        'name'               => 'required|max:120',
        'monthly_price'      => 'required|numeric|min:0',
        'max_meta_accounts'  => 'required|integer|min:1',
        'max_analyses_month' => 'required|integer|min:1',
        'auto_apply_allowed' => 'boolean',
    ];

    /**
     * @var array casts — type coercion for storage <-> PHP boundary
     */
    protected $casts = [
        'monthly_price'      => 'decimal:2',
        'max_meta_accounts'  => 'integer',
        'max_analyses_month' => 'integer',
        'auto_apply_allowed' => 'boolean',
    ];

    /**
     * @var array hasMany relations
     */
    public $hasMany = [
        'subscriptions' => Subscription::class,
    ];
}
