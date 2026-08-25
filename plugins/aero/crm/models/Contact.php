<?php namespace Aero\Crm\Models;

use Model;

class Contact extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_contacts';

    public $fillable = [
        'tenant_id', 'company_id', 'first_name', 'last_name', 'email', 'phone',
        'social_links', 'source', 'owner_id', 'shop_customer_id', 'hello_contact_id',
    ];

    public $jsonable = ['social_links'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'last_name' => 'required_without:first_name|max:255',
        'email'     => 'nullable|email|max:255',
    ];

    public $belongsTo = [
        'tenant'  => [\Aero\Sites\Models\Tenant::class],
        'company' => [Company::class],
        'owner'   => [\Backend\Models\User::class],
    ];

    public $hasMany = [
        'deals'            => [Deal::class],
        'activities'       => [Activity::class, 'key' => 'related_id', 'conditions' => "related_type = 'Aero\\\\Crm\\\\Models\\\\Contact'"],
        'collectionItems'  => [CollectionItem::class],
    ];

    public $belongsToMany = [
        'contactLists' => [
            ContactList::class,
            'table'    => 'aero_crm_contact_list_contact',
            'key'      => 'contact_id',
            'otherKey' => 'contact_list_id',
        ],
    ];

    public function __construct(array $attributes = [])
    {
        if (class_exists(\Aero\Shop\Models\Customer::class)) {
            $this->belongsTo['shopCustomer'] = [
                \Aero\Shop\Models\Customer::class,
                'key' => 'shop_customer_id',
            ];
        }

        if (class_exists(\Aero\Hello\Models\Contact::class)) {
            $this->belongsTo['helloContact'] = [
                \Aero\Hello\Models\Contact::class,
                'key' => 'hello_contact_id',
            ];
        }

        parent::__construct($attributes);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Opciones para el filtro de lista "Responsable" (config_filter.yaml).
     */
    public function getOwnerIdOptions()
    {
        return \Backend\Models\User::orderBy('first_name')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => trim($user->first_name . ' ' . $user->last_name) ?: $user->login])
            ->toArray();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->email ?: "Contacto #{$this->id}");
    }
}
