<?php namespace Aero\Crm\Models;

use Model;

class Contact extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Crm\Classes\HasTenantOwnerOptions;

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
     * Filtra las opciones de los campos de relación del formulario de cobros
     * (Contacto / Contactos adicionales) al tenant del registro en edición —
     * el Relation widget le pasa el modelo, no un id.
     */
    public function scopeBelongingToTenant($query, $model)
    {
        return $model && $model->tenant_id
            ? $query->where('tenant_id', $model->tenant_id)
            : $query;
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
        $this->linkShopCustomer();
        $this->syncHelloContact();
    }

    public function afterUpdate()
    {
        // Solo si cambió el dato con el que se busca: quien desvincula a mano
        // un cliente no lo ve reaparecer en cada guardado.
        if ($this->isDirty('phone') || $this->isDirty('email')) {
            $this->linkShopCustomer();
        }

        $this->syncHelloContact();
    }

    /**
     * Si el email o el teléfono del contacto es de un cliente de la tienda del
     * mismo tenant, lo enlaza (shop_customer_id) en vez de dejarlo vacío, y
     * completa nombre/email solo si el contacto todavía no los tiene.
     */
    public function linkShopCustomer(): void
    {
        if ($this->shop_customer_id || !$this->tenant_id || !class_exists(\Aero\Shop\Models\Customer::class)) {
            return;
        }

        $customer = \Aero\Crm\Classes\ShopCustomerSync::findCustomer((int) $this->tenant_id, $this->email, $this->phone);
        if (!$customer) {
            return;
        }

        $changes = ['shop_customer_id' => $customer->id];

        $nameless = \Aero\Hello\Models\Contact::isPlaceholderName(trim("{$this->first_name} {$this->last_name}"));
        if ($nameless && trim("{$customer->first_name} {$customer->last_name}") !== '') {
            $changes['first_name'] = $customer->first_name;
            $changes['last_name'] = $customer->last_name;
        }

        if (!$this->email && $customer->email) {
            $changes['email'] = $customer->email;
        }

        foreach ($changes as $key => $value) {
            $this->{$key} = $value;
        }
        $this->newQuery()->where('id', $this->id)->update($changes);
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

        // Lo que se escribe acá no debe rebotar hacia el CRM (Hello → CRM).
        \Aero\Crm\Classes\HelloSync::quietly(function () {
            $helloContact = $this->hello_contact_id
                ? \Aero\Hello\Models\Contact::find($this->hello_contact_id)
                : null;

            // El número ya chateó con este tenant: se enlaza ese contacto (con
            // su historial) en vez de crear un espejo duplicado.
            if (!$helloContact && ($phone = \Aero\Hello\Classes\PhoneNumber::normalize($this->phone))) {
                $known = \Aero\Hello\Models\ContactIdentity::where('platform', 'whatsapp')
                    ->whereIn('external_id', [$phone, '+' . $phone])
                    ->first()?->contact;

                $taken = $known && static::where('hello_contact_id', $known->id)->where('id', '!=', $this->id)->exists();

                if ($known && !$taken && (!$known->tenant_id || (int) $known->tenant_id === (int) $this->tenant_id)) {
                    $helloContact = $known;
                    $helloContact->tenant_id = $helloContact->tenant_id ?: $this->tenant_id;
                    $this->hello_contact_id = $helloContact->id;
                    $this->newQuery()->where('id', $this->id)->update(['hello_contact_id' => $helloContact->id]);
                }
            }

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
            elseif ($helloContact->name !== $this->full_name && !($this->isNamePlaceholder() && !$helloContact->hasPlaceholderName())) {
                $helloContact->name = $this->full_name;
                $helloContact->save();
            }
            elseif ($helloContact->isDirty()) {
                $helloContact->save();
            }

            $this->syncWhatsappIdentity($helloContact);
        });
    }

    /**
     * Nombre del CRM sin información real (vacío o solo un teléfono): no debe
     * pisar el nombre de WhatsApp que ya tiene el contacto de chat.
     */
    protected function isNamePlaceholder(): bool
    {
        return \Aero\Hello\Models\Contact::isPlaceholderName(trim("{$this->first_name} {$this->last_name}"));
    }

    protected function syncWhatsappIdentity(\Aero\Hello\Models\Contact $helloContact): void
    {
        // Formato único de Hello: solo dígitos internacionales, sin "+".
        $phone = \Aero\Hello\Classes\PhoneNumber::normalize($this->phone);
        if (!$phone) {
            return;
        }

        $identity = $helloContact->identities()->where('platform', 'whatsapp')->first();

        if (!$identity) {
            // El número ya es de otro contacto de chat (otro tenant, o uno ya
            // enlazado a otro contacto del CRM): no se duplica la identidad.
            $taken = \Aero\Hello\Models\ContactIdentity::where('platform', 'whatsapp')->where('external_id', $phone)->exists();

            if (!$taken) {
                $helloContact->identities()->create(['platform' => 'whatsapp', 'external_id' => $phone]);
            }
        }
        elseif ($identity->external_id !== $phone) {
            // No se renombra si otro contacto de chat ya usa ese número.
            $taken = \Aero\Hello\Models\ContactIdentity::where('platform', 'whatsapp')
                ->where('external_id', $phone)->where('id', '!=', $identity->id)->exists();

            if (!$taken) {
                $identity->external_id = $phone;
                $identity->save();
            }
        }
    }
}
