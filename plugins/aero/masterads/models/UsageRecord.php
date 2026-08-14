<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * UsageRecord — One metered consumption event against a Subscription.
 *
 * Written by `UsageMeter` whenever a billable action occurs (an analysis is
 * run, a sync executes, or a recommendation is applied). Read by
 * `PlanLimiter` to count usage within the current billing period.
 *
 * Validates: Requirements 9.1, 9.2, 9.7, 17.1, 17.6
 *
 * @property int    $id
 * @property int    $subscription_id
 * @property string $metric
 * @property int    $qty
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class UsageRecord extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table associated with the model
     */
    public $table = 'aero_masterads_usage_records';

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'subscription_id',
        'metric',
        'qty',
        'recorded_at',
    ];

    /**
     * @var array rules — validation, per Requirement 9.7
     */
    public $rules = [
        'subscription_id' => 'required|exists:aero_masterads_subscriptions,id',
        'metric'          => 'required|in:analysis,sync,applied_action',
        'qty'             => 'required|integer|min:1',
        'recorded_at'     => 'required|date',
    ];

    /**
     * @var array dates — Carbon-cast columns
     */
    protected $dates = [
        'recorded_at',
    ];

    /**
     * @var array belongsTo relations
     */
    public $belongsTo = [
        'subscription' => Subscription::class,
    ];
}
