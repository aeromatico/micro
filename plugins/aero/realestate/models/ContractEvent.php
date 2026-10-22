<?php namespace Aero\Realestate\Models;

use Model;

/** Bitácora del contrato: creación, activación, renovaciones, modificaciones, movimientos, documentos, disputa. */
class ContractEvent extends Model
{
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_contract_events';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'contract_id', 'type', 'summary', 'data', 'user_id', 'created_at'];

    public $jsonable = ['data'];

    public $dates = ['created_at'];

    public $belongsTo = ['contract' => [Contract::class, 'key' => 'contract_id']];

    public static function log(Contract $contract, string $type, string $summary, array $data = []): self
    {
        return static::create([
            'tenant_id'   => $contract->tenant_id,
            'contract_id' => $contract->id,
            'type'        => $type,
            'summary'     => $summary,
            'data'        => $data ?: null,
            'user_id'     => \BackendAuth::getUser()?->id,
            'created_at'  => now(),
        ]);
    }
}
