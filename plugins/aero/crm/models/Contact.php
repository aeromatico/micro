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

    /**
     * El CRM es la fuente de verdad del contacto; Hello solo necesita un
     * espejo (nombre + identidad de WhatsApp) para poder mandarle mensajes.
     * Se sincroniza solo en create/update — nunca al revés, Hello no edita
     * contactos del CRM.
     */
    public function afterCreate()
    {
        $this->syncHelloContact();
    }

    public function afterUpdate()
    {
        $this->syncHelloContact();
    }

    /**
     * A propósito NO se borra el contacto de Hello: en cascada se llevaría
     * puesto todo su historial de conversaciones y mensajes de WhatsApp
     * (aero_hello_conversations/messages tienen FK cascadeOnDelete sobre
     * contact_id). Queda huérfano — ya no editable desde el CRM, pero con su
     * historial intacto — y se avisa para que quede claro que no se borró.
     */
    public function afterDelete()
    {
        if ($this->hello_contact_id && class_exists(\Aero\Hello\Models\Contact::class)) {
            \Flash::warning('El contacto se borró del CRM. Su historial de mensajes en Hello no se borra: queda ahí, sin vincular.');
        }
    }

    public function syncHelloContact(): void
    {
        if (!class_exists(\Aero\Hello\Models\Contact::class)) {
            return;
        }

        $helloContact = $this->hello_contact_id
            ? \Aero\Hello\Models\Contact::find($this->hello_contact_id)
            : null;

        if (!$helloContact) {
            $helloContact = \Aero\Hello\Models\Contact::create([
                'tenant_id' => $this->tenant_id,
                'name'      => $this->full_name,
            ]);

            // Update directo por query builder: evita disparar otro
            // afterUpdate de este mismo modelo (recursión) por asignar y
            // guardar hello_contact_id con $this->save().
            $this->hello_contact_id = $helloContact->id;
            $this->newQuery()->where('id', $this->id)->update(['hello_contact_id' => $helloContact->id]);
        }
        elseif ($helloContact->name !== $this->full_name) {
            $helloContact->name = $this->full_name;
            $helloContact->save();
        }

        $this->syncWhatsappIdentity($helloContact);
    }

    protected function syncWhatsappIdentity(\Aero\Hello\Models\Contact $helloContact): void
    {
        $phone = $this->phone ? preg_replace('/[^0-9+]/', '', $this->phone) : null;
        if (!$phone) {
            return;
        }

        $identity = $helloContact->identities()->where('platform', 'whatsapp')->first();

        if (!$identity) {
            $helloContact->identities()->create(['platform' => 'whatsapp', 'external_id' => $phone]);
        }
        elseif ($identity->external_id !== $phone) {
            $identity->external_id = $phone;
            $identity->save();
        }
    }
}
