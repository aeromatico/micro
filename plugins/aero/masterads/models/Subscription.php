<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * Subscription — The active link between a Workspace and a Plan for a period.
 *
 * Drives quota enforcement: when a Workspace member runs an analysis or
 * applies a recommendation, the engine consults the active Subscription
 * (status `active` or `trialing`) and its `Plan` caps before proceeding.
 *
 * Validates: Requirements 9.1, 9.2, 9.7, 17.1, 17.6
 *
 * @property int    $id
 * @property int    $workspace_id
 * @property int    $plan_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @method static \October\Rain\Database\Builder active()
 */
class Subscription extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\MasterAds\Classes\Concerns\BelongsToTenantScope;

    /**
     * @var string table associated with the model
     */
    public $table = 'aero_masterads_subscriptions';

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'workspace_id',
        'plan_id',
        'status',
        'period_start',
        'period_end',
    ];

    /**
     * @var array rules — validation, per Requirement 9.2
     */
    public $rules = [
        'workspace_id' => 'required|exists:aero_masterads_workspaces,id',
        'plan_id'      => 'required|exists:aero_masterads_plans,id',
        'status'       => 'required|in:active,past_due,canceled,trialing',
        'period_start' => 'required|date',
        'period_end'   => 'required|date|after:period_start',
    ];

    /**
     * @var array dates — Carbon-cast columns
     */
    protected $dates = [
        'period_start',
        'period_end',
    ];

    /**
     * @var array belongsTo relations
     */
    public $belongsTo = [
        'workspace' => Workspace::class,
        'plan'      => Plan::class,
    ];

    /**
     * @var array hasMany relations
     */
    public $hasMany = [
        'usage_records' => UsageRecord::class,
    ];

    /**
     * scopeActive limits the query to subscriptions that are billing-active.
     *
     * "Active" here means `status IN ('active','trialing')`, which is the
     * gate used by `PlanLimiter` and the recommendation engine.
     *
     * @param  \October\Rain\Database\Builder $query
     * @return \October\Rain\Database\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trialing']);
    }
}
