<?php namespace Aero\MasterAds\Models;

use Model;
use October\Rain\Exception\ApplicationException;

/**
 * AppliedAction — Audit trail inmutable de una aplicación efectiva (o
 * intento) de una {@see Recommendation} sobre la Meta Graph API.
 *
 * Reglas de negocio críticas:
 *
 *  - Append-only: una vez creado, el registro NO puede modificarse ni
 *    eliminarse desde la lógica del plugin. Las sobreescrituras de
 *    {@see self::beforeUpdate()} y {@see self::beforeDelete()} lanzan
 *    {@see ApplicationException} para hacer cumplir el contrato
 *    (Requirement 8.3).
 *  - Unicidad de éxito: existe a lo sumo un AppliedAction con
 *    `success = true` por Recommendation, garantizado por un índice único
 *    sobre (`recommendation_id`, `success`) en la migración
 *    (Requirement 7.12, 14.4).
 *  - Trazabilidad completa: `before_state`, `after_state` y `meta_response`
 *    persisten el contexto antes/después y la respuesta cruda de Meta
 *    (Requirements 8.1, 8.2).
 *
 * Validates: Requirements 7.12, 8.1, 8.2, 8.3, 17.1, 17.6.
 *
 * @property int                            $id
 * @property int                            $recommendation_id
 * @property int                            $applied_by        FK a backend_users
 * @property bool                           $success
 * @property array                          $before_state
 * @property array|null                     $after_state
 * @property array|null                     $meta_response
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AppliedAction extends Model
{
    // Tenant-scoped indirectly via parent relation (no direct workspace_id column).
    // Access is filtered through Recommendation → AiAnalysis → Workspace; see BelongsToTenantScope.
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table associated with the model
     */
    public $table = 'aero_masterads_applied_actions';

    /**
     * @var array fillable attributes for mass assignment
     */
    protected $fillable = [
        'recommendation_id',
        'applied_by',
        'success',
        'before_state',
        'after_state',
        'meta_response',
    ];

    /**
     * @var array jsonable JSON columns auto-cast to/from array
     */
    public $jsonable = [
        'before_state',
        'after_state',
        'meta_response',
    ];

    /**
     * @var array casts native attribute casts
     */
    protected $casts = [
        'success' => 'boolean',
    ];

    /**
     * @var array rules validation rules (Requirements 8.1, 8.2)
     */
    public $rules = [
        'recommendation_id' => 'required|exists:aero_masterads_recommendations,id',
        'applied_by'        => 'required|exists:backend_users,id',
        'success'           => 'required|boolean',
    ];

    /**
     * @var array belongsTo relations
     */
    public $belongsTo = [
        'recommendation' => Recommendation::class,
        'user'           => [\Backend\Models\User::class, 'key' => 'applied_by'],
    ];

    /**
     * Append-only enforcement — block updates from plugin code.
     *
     * Once persisted, an AppliedAction documents what happened: changing
     * it would forge the audit history. Requirement 8.3 explicitly demands
     * the row be immutable, so we throw an {@see ApplicationException} from
     * the `beforeUpdate` hook. The exception bubbles up to the backend
     * Form/ListController and is surfaced to the user as a flash message
     * without leaking domain internals.
     *
     * @throws ApplicationException always.
     */
    public function beforeUpdate(): void
    {
        throw new ApplicationException(
            'AppliedAction is immutable (append-only audit trail)'
        );
    }

    /**
     * Append-only enforcement — block deletes from plugin code.
     *
     * Mirror of {@see self::beforeUpdate()} for deletions. The audit trail
     * must survive the lifetime of its parent Recommendation; physical
     * removal is reserved for DB-level retention policies, never for
     * application-level operations (Requirement 8.3).
     *
     * @throws ApplicationException always.
     */
    public function beforeDelete(): void
    {
        throw new ApplicationException(
            'AppliedAction is immutable (append-only audit trail)'
        );
    }

    /**
     * Options accessor for the `applied_by` dropdown in the form widget.
     *
     * Backend users are surfaced by their login so reviewers can audit who
     * applied each recommendation.
     *
     * @return array<int, string>
     */
    public function getAppliedByOptions(): array
    {
        return \Backend\Models\User::orderBy('login')
            ->pluck('login', 'id')
            ->toArray();
    }
}
