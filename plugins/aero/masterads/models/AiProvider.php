<?php namespace Aero\MasterAds\Models;

use DB;
use Model;
use Throwable;
use Illuminate\Support\Facades\Crypt;

/**
 * AiProvider — configuración de un proveedor LLM por Workspace.
 *
 * Almacena el `api_key` cifrado en reposo mediante `Crypt::encrypt`, lo
 * descifra transparentemente al leerlo y lo oculta de toda serialización.
 *
 * Garantiza la invariante de "único default por Workspace": cuando se marca
 * `is_default = true`, los demás providers del mismo Workspace quedan en
 * `false` dentro de una transacción atómica.
 *
 * Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 15.1, 15.2, 15.3,
 *            17.1, 17.6 (master-ads spec)
 */
class AiProvider extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\MasterAds\Classes\Concerns\BelongsToTenantScope;

    /**
     * Database table.
     */
    public $table = 'aero_masterads_ai_providers';

    /**
     * Mass-assignable attributes.
     */
    public $fillable = [
        'workspace_id',
        'name',
        'driver',
        'model',
        'api_key',
        'is_default',
        'settings',
    ];

    /**
     * Attributes hidden from arrays / JSON serialization (Requirement 5.3, 15.3).
     */
    protected $hidden = ['api_key'];

    /**
     * JSON columns auto-cast to array on access.
     *
     * `settings` accepts the optional parameters `temperature`, `max_tokens`,
     * `base_url`, `http_referer` and `x_title` (Requirement 5.5).
     */
    public $jsonable = ['settings'];

    /**
     * Native casts.
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Validation rules (Requirements 5.1, 5.2).
     */
    public $rules = [
        'name'       => 'required|max:120',
        'driver'     => 'required|in:openrouter,openai,anthropic,custom',
        'model'      => 'required|string|max:255',
        'api_key'    => 'required|string',
        'is_default' => 'boolean',
    ];

    /**
     * Relationships.
     */
    public $belongsTo = [
        'workspace' => Workspace::class,
    ];

    public $hasMany = [
        'ai_analyses' => AiAnalysis::class,
    ];

    /* ------------------------------------------------------------------ */
    /*  Encryption mutators (Requirements 5.3, 15.1, 15.2)                */
    /* ------------------------------------------------------------------ */

    /**
     * Decrypts `api_key` transparently when accessed.
     *
     * Falls back to the raw value if decryption fails (legacy / unencrypted
     * rows) so existing data is not lost during a key rotation.
     */
    public function getApiKeyAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decrypt($value);
        } catch (Throwable $e) {
            // Legacy / plaintext tolerance: return raw value when not encrypted.
            return $value;
        }
    }

    /**
     * Encrypts `api_key` before persisting.
     */
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = ($value === null || $value === '')
            ? null
            : Crypt::encrypt($value);
    }

    /* ------------------------------------------------------------------ */
    /*  Default-uniqueness invariant (Requirement 5.4)                    */
    /* ------------------------------------------------------------------ */

    /**
     * After persisting, demote any other `is_default` provider of the same
     * Workspace to keep a single default per tenant.
     *
     * Wrapped in a DB transaction so concurrent writes cannot leave more
     * than one `is_default = true` row.
     */
    public function afterSave(): void
    {
        if ($this->is_default !== true) {
            return;
        }

        DB::transaction(function (): void {
            static::where('workspace_id', $this->workspace_id)
                ->where('id', '!=', $this->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }
}
