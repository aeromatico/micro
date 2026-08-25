<?php namespace Aero\Crm\Models;

use Model;

class ContactList extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_contact_lists';

    public $fillable = ['tenant_id', 'name', 'description', 'color'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|max:255',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public $belongsToMany = [
        'contacts' => [
            Contact::class,
            'table'    => 'aero_crm_contact_list_contact',
            'key'      => 'contact_list_id',
            'otherKey' => 'contact_id',
        ],
    ];

    public $hasMany = [
        'collectionItems' => [CollectionItem::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getContactsCountAttribute(): int
    {
        return $this->contacts()->count();
    }
}
