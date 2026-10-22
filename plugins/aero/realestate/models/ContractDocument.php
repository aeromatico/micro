<?php namespace Aero\Realestate\Models;

use Model;

/** Documento de respaldo del anticrético. La verificación administrativa solo la hace quien administra (no el agente). */
class ContractDocument extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_contract_documents';

    public $fillable = ['tenant_id', 'contract_id', 'type', 'number', 'issued_on', 'status', 'notes'];

    public $rules = [
        'contract_id' => 'required|integer',
        'type'        => 'required|in:public_deed,property_registry,folio,id_document,other',
        'status'      => 'required|in:pending,verified,rejected',
    ];

    public $attributes = ['status' => 'pending'];

    public $dates = ['issued_on', 'verified_at'];

    public $belongsTo = ['contract' => [Contract::class, 'key' => 'contract_id']];

    public $attachOne = ['file' => \System\Models\File::class];

    public static array $types = [
        'public_deed'       => 'Escritura pública',
        'property_registry' => 'Inscripción en Derechos Reales',
        'folio'             => 'Folio real / certificado de propiedad',
        'id_document'       => 'Documento de identidad de las partes',
        'other'             => 'Otro respaldo',
    ];

    public static array $statuses = ['pending' => 'Pendiente de revisión', 'verified' => 'Verificado', 'rejected' => 'Observado'];

    /** Los que deben estar verificados para dar por regular un anticrético vigente. */
    public const REQUIRED = ['public_deed', 'property_registry'];

    public function getTypeOptions(): array
    {
        return self::$types;
    }

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function beforeValidate(): void
    {
        if ($c = Contract::find($this->contract_id)) {
            $this->tenant_id = $c->tenant_id;
        }
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible(['contract_id' => Contract::class]);

        if (($this->isDirty('status') || !$this->exists) && $this->status !== 'pending') {
            $user = \BackendAuth::getUser();
            if ($user && !$user->hasAccess(['aero.realestate.use', 'aero.realestate.superadmin'], false)) {
                throw new \ApplicationException('Solo la administración de la inmobiliaria verifica documentos.');
            }
            $this->verified_by = $user?->id;
            $this->verified_at = now();
        }
        if ($this->status === 'pending') {
            $this->verified_by = $this->verified_at = null;
        }
    }

    public function afterSave(): void
    {
        if ($this->wasRecentlyCreated || $this->wasChanged('status')) {
            ContractEvent::log(Contract::findOrFail($this->contract_id), 'document',
                (self::$types[$this->type] ?? $this->type) . ': ' . mb_strtolower(self::$statuses[$this->status] ?? $this->status) . '.');
        }
    }
}
