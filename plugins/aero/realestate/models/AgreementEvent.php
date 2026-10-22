<?php namespace Aero\Realestate\Models;

use Model;

/** Historial del contrato: generación, revisión, aprobación, firma, renovaciones y cambios. */
class AgreementEvent extends Model
{
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_agreement_events';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'agreement_id', 'type', 'summary', 'data', 'user_id', 'created_at'];

    public $jsonable = ['data'];

    public $dates = ['created_at'];

    public $belongsTo = ['agreement' => [Agreement::class, 'key' => 'agreement_id']];

    public static function log(Agreement $a, string $type, string $summary, array $data = []): self
    {
        return static::create([
            'tenant_id' => $a->tenant_id, 'agreement_id' => $a->id, 'type' => $type, 'summary' => $summary,
            'data' => $data ?: null, 'user_id' => \BackendAuth::getUser()?->id, 'created_at' => now(),
        ]);
    }
}
