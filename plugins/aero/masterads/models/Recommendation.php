<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * Recommendation — Sugerencia atómica generada por la IA dentro de un
 * {@see AiAnalysis}, representando un cambio concreto y aplicable sobre la
 * jerarquía Meta (ajuste de presupuesto, pausa, escalado, etc.).
 *
 * El `payload` JSON contiene los parámetros del cambio (validados contra el
 * schema específico de cada `action_type` por `RecommendationValidator`),
 * mientras que `expected_impact` documenta la mejora prevista (impresiones,
 * clicks, conversiones, ROAS) para informar al revisor.
 *
 * Una Recommendation puede tener a lo sumo un {@see AppliedAction} con
 * `success = true` (Requirement 7.12, garantizado por índice único parcial
 * sobre (`recommendation_id`, `success`) en la migración).
 *
 * Validates: Requirements 6.7, 6.10, 7.12, 8.1, 8.2, 17.1, 17.6.
 *
 * @property int                            $id
 * @property int                            $ai_analysis_id
 * @property string                         $action_type   adjust_budget|pause|resume|scale|change_audience|change_creative
 * @property string                         $severity      low|medium|high|critical
 * @property string                         $status        pending|approved|rejected|applied|failed
 * @property string                         $rationale
 * @property array                          $payload
 * @property array|null                     $expected_impact
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Recommendation extends Model
{
    // Tenant-scoped indirectly via parent relation (no direct workspace_id column).
    // Access is filtered through AiAnalysis → Workspace; see BelongsToTenantScope.
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table associated with the model
     */
    public $table = 'aero_masterads_recommendations';

    /**
     * @var array fillable attributes for mass assignment
     */
    protected $fillable = [
        'ai_analysis_id',
        'action_type',
        'severity',
        'status',
        'rationale',
        'payload',
        'expected_impact',
    ];

    /**
     * @var array jsonable JSON columns auto-cast to/from array
     */
    public $jsonable = [
        'payload',
        'expected_impact',
    ];

    /**
     * @var array rules validation rules (Requirement 6.7)
     */
    public $rules = [
        'ai_analysis_id' => 'required|exists:aero_masterads_ai_analyses,id',
        'action_type'    => 'required|in:adjust_budget,pause,resume,scale,change_audience,change_creative',
        'severity'       => 'required|in:low,medium,high,critical',
        'status'         => 'required|in:pending,approved,rejected,applied,failed',
        'rationale'      => 'required|string',
    ];

    /**
     * @var array belongsTo relations
     */
    public $belongsTo = [
        'ai_analysis' => AiAnalysis::class,
    ];

    /**
     * @var array hasOne relations
     *
     * The audit row of a successful (or failed) application — at most one
     * with `success = true` exists per recommendation (Requirement 7.12).
     */
    public $hasOne = [
        'applied_action' => AppliedAction::class,
    ];

    /**
     * Action type dropdown options (Requirement 6.7).
     *
     * @return array<string, string>
     */
    public function getActionTypeOptions(): array
    {
        return [
            'adjust_budget'   => 'aero.masterads::lang.action_type.adjust_budget',
            'pause'           => 'aero.masterads::lang.action_type.pause',
            'resume'          => 'aero.masterads::lang.action_type.resume',
            'scale'           => 'aero.masterads::lang.action_type.scale',
            'change_audience' => 'aero.masterads::lang.action_type.change_audience',
            'change_creative' => 'aero.masterads::lang.action_type.change_creative',
        ];
    }

    /**
     * Severity dropdown options (Requirement 6.7).
     *
     * @return array<string, string>
     */
    public function getSeverityOptions(): array
    {
        return [
            'low'      => 'aero.masterads::lang.severity.low',
            'medium'   => 'aero.masterads::lang.severity.medium',
            'high'     => 'aero.masterads::lang.severity.high',
            'critical' => 'aero.masterads::lang.severity.critical',
        ];
    }

    /**
     * Status dropdown options (Requirement 6.7).
     *
     * @return array<string, string>
     */
    public function getStatusOptions(): array
    {
        return [
            'pending'  => 'aero.masterads::lang.rec_status.pending',
            'approved' => 'aero.masterads::lang.rec_status.approved',
            'rejected' => 'aero.masterads::lang.rec_status.rejected',
            'applied'  => 'aero.masterads::lang.rec_status.applied',
            'failed'   => 'aero.masterads::lang.rec_status.failed',
        ];
    }
}
