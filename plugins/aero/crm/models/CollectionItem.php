<?php namespace Aero\Crm\Models;

use Model;

/**
 * Un cobro pendiente/pagado. El módulo de Cobranzas lo usa tanto para el
 * seguimiento manual (marcar como pagado) como para los recordatorios
 * automáticos (ver Classes\Collections\CollectionReminderGenerator).
 *
 * Los destinatarios del cobro son uno o varios Contact a través de la
 * relación `recipients` (tabla pivote aero_crm_collection_item_contact) y
 * puede agruparse en una o varias listas vía `contactLists` (tabla pivote
 * aero_crm_collection_item_contact_list). `contact_id` y `contact_list_id`
 * son legados de cuando había un único contacto y una única lista: quedaron
 * opcionales y se conservan para no perder el histórico.
 */
class CollectionItem extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_collection_items';

    public $fillable = [
        'tenant_id', 'contact_id', 'contact_list_id', 'owner_id',
        'concept', 'amount', 'currency', 'due_date', 'status', 'notes',
        'payment_reference',
    ];

    protected $dates = ['due_date', 'paid_at', 'last_reminder_at'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'concept'   => 'required|max:255',
        'amount'    => 'required|numeric|min:0',
        'due_date'  => 'required|date',
    ];

    public $belongsTo = [
        'tenant'      => [\Aero\Sites\Models\Tenant::class],
        'contact'     => [Contact::class],
        'contactList' => [ContactList::class],
        'owner'       => [\Backend\Models\User::class],
    ];

    public $hasMany = [
        'reminderLogs' => [CollectionReminderLog::class],
    ];

    public $belongsToMany = [
        'recipients' => [
            Contact::class,
            'table'    => 'aero_crm_collection_item_contact',
            'key'      => 'collection_item_id',
            'otherKey' => 'contact_id',
        ],
        'contactLists' => [
            ContactList::class,
            'table'    => 'aero_crm_collection_item_contact_list',
            'key'      => 'collection_item_id',
            'otherKey' => 'contact_list_id',
        ],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')->where('due_date', '<', now()->toDateString());
    }

    public function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'paid'    => 'Pagado',
            'void'    => 'Anulado',
        ];
    }

    public function getContactListIdOptions(): array
    {
        return ContactList::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Filtro del listado de cobros por lista (relación muchos-a-muchos). El
     * Filter widget entrega el FilterScope con el valor elegido; se usa
     * whereHas para incluir los cobros que tengan esa lista entre las suyas.
     */
    public function scopeFilterContactList($query, $filter)
    {
        $value = $filter instanceof \Backend\Classes\FilterScope ? $filter->value : $filter;
        if (!$value) {
            return $query;
        }

        return $query->whereHas('contactLists', function ($q) use ($value) {
            $q->whereIn('aero_crm_contact_lists.id', (array) $value);
        });
    }

    public function markAsPaid(): void
    {
        $this->status  = 'paid';
        $this->paid_at = now();
        $this->save();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date && $this->due_date->lt(now()->startOfDay());
    }

    /**
     * El QrCode de aero/qrbo generado para este cobro (ver
     * Classes\Collections\CollectionQrIssuer), si tiene uno y el plugin está
     * instalado. No es una relación Eloquent porque `payment_reference`
     * apunta a `QrCode.internal_reference`, no a un id.
     */
    public function getQrCode(): ?\Aero\Qrbo\Models\QrCode
    {
        if (!$this->payment_reference || !class_exists(\Aero\Qrbo\Models\QrCode::class)) {
            return null;
        }

        return \Aero\Qrbo\Models\QrCode::where('internal_reference', $this->payment_reference)->first();
    }
}
