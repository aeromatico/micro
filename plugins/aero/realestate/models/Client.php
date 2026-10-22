<?php namespace Aero\Realestate\Models;

use Model;

/** Cliente interesado en comprar, alquilar o tomar en anticrético. El usuario de RainLab.User (user_id) es opcional y se crea desde el formulario. */
class Client extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_clients';

    public $fillable = ['civil_status', 'nationality', 'address', 'tenant_id', 'user_id', 'name', 'email', 'phone', 'document', 'notes', 'interests', 'budget_min', 'budget_max', 'currency', 'preferences', 'agent_id', 'source'];

    public $rules = [
        'name'  => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
    ];

    public $jsonable = ['interests', 'preferences'];

    public $belongsTo = ['agent' => [Agent::class, 'key' => 'agent_id']];

    public function beforeSave(): void
    {
        $this->assertReferencesVisible(['agent_id' => Agent::class]);
    }

    public function getInterestsOptions(): array
    {
        return Offer::$modalities;
    }

    public function getAgentIdOptions(): array
    {
        return Agent::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value === null || $value === '' ? null : preg_replace('/[^\d+]/', '', (string) $value);
    }
}
