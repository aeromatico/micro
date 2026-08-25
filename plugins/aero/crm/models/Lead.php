<?php namespace Aero\Crm\Models;

use Model;

class Lead extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_leads';

    public $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'company_name', 'source',
        'status', 'owner_id', 'converted_contact_id', 'converted_deal_id',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|max:255',
        'email'     => 'nullable|email|max:255',
        'status'    => 'required|in:new,contacted,qualified,disqualified',
    ];

    public $belongsTo = [
        'tenant'           => [\Aero\Sites\Models\Tenant::class],
        'owner'            => [\Backend\Models\User::class],
        'convertedContact' => [Contact::class, 'key' => 'converted_contact_id'],
        'convertedDeal'    => [Deal::class, 'key' => 'converted_deal_id'],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Crea (o reutiliza) Company + Contact y un Deal en la primera etapa del
     * pipeline del tenant. Idempotente: si el lead ya fue convertido, devuelve
     * el Contact/Deal existentes en vez de duplicar.
     */
    public function convert(): array
    {
        if ($this->converted_contact_id && $this->converted_deal_id) {
            return ['contact' => $this->convertedContact, 'deal' => $this->convertedDeal];
        }

        $company = null;
        if ($this->company_name) {
            $company = Company::firstOrCreate(
                ['tenant_id' => $this->tenant_id, 'name' => $this->company_name],
                ['owner_id' => $this->owner_id]
            );
        }

        $contact = Contact::create([
            'tenant_id'  => $this->tenant_id,
            'company_id' => $company?->id,
            'first_name' => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'source'     => $this->source,
            'owner_id'   => $this->owner_id,
        ]);

        $pipeline = Pipeline::seedDefaultForTenant($this->tenant_id);
        $firstStage = $pipeline->stages()->orderBy('sort_order')->first();

        $deal = Deal::create([
            'tenant_id'   => $this->tenant_id,
            'pipeline_id' => $pipeline->id,
            'stage_id'    => $firstStage->id,
            'contact_id'  => $contact->id,
            'company_id'  => $company?->id,
            'title'       => $this->name,
            'owner_id'    => $this->owner_id,
        ]);

        $this->converted_contact_id = $contact->id;
        $this->converted_deal_id = $deal->id;
        $this->status = 'qualified';
        $this->save();

        return ['contact' => $contact, 'deal' => $deal];
    }
}
