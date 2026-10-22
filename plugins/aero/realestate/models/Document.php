<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Archivo documental de una propiedad, propietario, cliente o contrato: cédula, folio real, impuestos, poderes…
 * El archivo se guarda PRIVADO (no tiene URL pública) y solo se descarga por el panel con permiso.
 * La verificación la hace la administración; lo pendiente alimenta las alertas.
 */
class Document extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_documents';

    public $fillable = ['tenant_id', 'property_id', 'owner_id', 'client_id', 'agreement_id', 'type', 'name', 'number', 'issued_on', 'expires_on', 'status', 'notes'];

    public $rules = [
        'type'   => 'required|in:id_card,title_deed,tax,utility,plan,power_of_attorney,contract,other',
        'name'   => 'required|string|max:255',
        'status' => 'required|in:pending,verified,rejected',
    ];

    public $attributes = ['status' => 'pending'];

    public $dates = ['issued_on', 'expires_on', 'verified_at', 'expiry_alerted_at'];

    public $belongsTo = [
        'property'  => [Property::class, 'key' => 'property_id'],
        'owner'     => [Owner::class, 'key' => 'owner_id'],
        'client'    => [Client::class, 'key' => 'client_id'],
        'agreement' => [Agreement::class, 'key' => 'agreement_id'],
    ];

    public $attachOne = ['file' => [\System\Models\File::class, 'public' => false]];

    public static array $types = [
        'id_card' => 'Documento de identidad', 'title_deed' => 'Folio real / título de propiedad', 'tax' => 'Impuestos (IPBI / catastro)',
        'utility' => 'Servicios básicos', 'plan' => 'Planos aprobados', 'power_of_attorney' => 'Poder', 'contract' => 'Contrato firmado', 'other' => 'Otro',
    ];

    public static array $statuses = ['pending' => 'Pendiente de revisión', 'verified' => 'Verificado', 'rejected' => 'Observado'];

    public function getTypeOptions(): array
    {
        return self::$types;
    }

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function getPropertyIdOptions(): array
    {
        return Property::visible()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function getOwnerIdOptions(): array
    {
        return Owner::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getClientIdOptions(): array
    {
        return Client::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getAgreementIdOptions(): array
    {
        return Agreement::visible()->orderByDesc('id')->limit(300)->get()->mapWithKeys(fn ($a) => [$a->id => $a->code . ' · ' . $a->title])->all();
    }

    public function getTypeNameAttribute(): string
    {
        return self::$types[$this->type] ?? (string) $this->type;
    }

    public function getStatusNameAttribute(): string
    {
        return self::$statuses[$this->status] ?? (string) $this->status;
    }

    /** A quién pertenece, en una línea. */
    public function getSubjectAttribute(): string
    {
        return collect([
            $this->property ? 'Propiedad: ' . $this->property->title : null,
            $this->owner ? 'Propietario: ' . $this->owner->name : null,
            $this->client ? 'Cliente: ' . $this->client->name : null,
            $this->agreement ? 'Contrato: ' . $this->agreement->code : null,
        ])->filter()->implode(' · ');
    }

    public function beforeValidate(): void
    {
        foreach ([[Property::class, 'property_id'], [Owner::class, 'owner_id'], [Client::class, 'client_id'], [Agreement::class, 'agreement_id']] as [$class, $col]) {
            if ($this->{$col} && ($tenant = $class::whereKey($this->{$col})->value('tenant_id'))) {
                $this->tenant_id = $tenant;
                break;
            }
        }
    }

    public function beforeSave(): void
    {
        if (!$this->property_id && !$this->owner_id && !$this->client_id && !$this->agreement_id) {
            throw new \ApplicationException('Indica a qué pertenece el documento: propiedad, propietario, cliente o contrato.');
        }
        $this->assertReferencesVisible([
            'property_id' => Property::class, 'owner_id' => Owner::class, 'client_id' => Client::class, 'agreement_id' => Agreement::class,
        ]);

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
        if (!$this->exists) {
            $this->uploaded_by = \BackendAuth::getUser()?->id;
        }
        if ($this->isDirty('expires_on')) {
            $this->expiry_alerted_at = null;
        }
    }
}
