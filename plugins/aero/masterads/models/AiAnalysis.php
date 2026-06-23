<?php namespace Aero\MasterAds\Models;

use Model;

/**
 * AiAnalysis — Una corrida de análisis IA sobre un target (campaign|adset|ad).
 *
 * Agrupa N {@see Recommendation} hijas y persiste todo el contexto necesario
 * para reproducir el análisis: el `prompt_payload` enviado al LLM, la
 * `raw_response` cruda recibida, el snapshot agregado de métricas
 * (`metrics_snapshot`) sobre el que se construyó el prompt, los tokens
 * consumidos y el costo en USD.
 *
 * Aplica `SoftDelete` para preservar el histórico de análisis incluso ante
 * borrados lógicos desde el backend (Requirement 8.6) — el audit trail
 * nunca debería perderse de forma silenciosa.
 *
 * Validates: Requirements 6.7, 6.10, 7.12, 8.1, 8.2, 8.3, 8.5, 8.6,
 *            17.1, 17.6 (master-ads spec).
 *
 * @property int                            $id
 * @property int                            $workspace_id
 * @property int                            $ai_provider_id
 * @property string                         $target_type     campaign|adset|ad
 * @property int                            $target_id
 * @property string                         $status          queued|running|success|failed
 * @property array|null                     $prompt_payload
 * @property array|null                     $raw_response
 * @property array|null                     $metrics_snapshot
 * @property int                            $tokens_used
 * @property float                          $cost_usd
 * @property string|null                    $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class AiAnalysis extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\MasterAds\Classes\Concerns\BelongsToTenantScope;

    /**
     * @var string table associated with the model
     */
    public $table = 'aero_masterads_ai_analyses';

    /**
     * @var array fillable attributes for mass assignment
     */
    protected $fillable = [
        'workspace_id',
        'ai_provider_id',
        'target_type',
        'target_id',
        'status',
        'prompt_payload',
        'raw_response',
        'metrics_snapshot',
        'tokens_used',
        'cost_usd',
        'error_message',
    ];

    /**
     * @var array jsonable JSON columns auto-cast to/from array
     *
     * Reproducibility (Requirement 6.10, 8.5) requires the prompt and raw
     * response of every analysis to be stored as structured JSON so a
     * subsequent run can be replayed against the same provider.
     */
    public $jsonable = [
        'prompt_payload',
        'raw_response',
        'metrics_snapshot',
    ];

    /**
     * @var array dates Carbon-cast columns
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array rules validation rules (Requirements 6.1, 6.7)
     */
    public $rules = [
        'workspace_id'   => 'required|exists:aero_masterads_workspaces,id',
        'ai_provider_id' => 'required|exists:aero_masterads_ai_providers,id',
        'target_type'    => 'required|in:campaign,adset,ad',
        'target_id'      => 'required|integer',
        'status'         => 'required|in:queued,running,success,failed',
    ];

    /**
     * @var array belongsTo relations
     */
    public $belongsTo = [
        'workspace' => Workspace::class,
        'provider'  => [AiProvider::class, 'key' => 'ai_provider_id'],
    ];

    /**
     * @var array hasMany relations
     */
    public $hasMany = [
        'recommendations' => Recommendation::class,
    ];

    /**
     * Status dropdown options for the form widget.
     *
     * @return array<string, string>
     */
    public function getStatusOptions(): array
    {
        return [
            'queued'  => 'aero.masterads::lang.status.queued',
            'running' => 'aero.masterads::lang.status.running',
            'success' => 'aero.masterads::lang.status.success',
            'failed'  => 'aero.masterads::lang.status.failed',
        ];
    }

    /**
     * Target type dropdown options for the form widget.
     *
     * @return array<string, string>
     */
    public function getTargetTypeOptions(): array
    {
        return [
            'campaign' => 'aero.masterads::lang.target_type.campaign',
            'adset'    => 'aero.masterads::lang.target_type.adset',
            'ad'       => 'aero.masterads::lang.target_type.ad',
        ];
    }
}
