<?php namespace Aero\Crm\Models;

use Model;

/**
 * Un cobro pendiente/pagado asociado a un Contact. El módulo de Cobranzas
 * lo usa tanto para el seguimiento manual (marcar como pagado) como para
 * los recordatorios automáticos (ver Classes\Collections\CollectionReminderGenerator).
 */
class CollectionItem extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_collection_items';

    public $fillable = [
        'tenant_id', 'contact_id', 'contact_list_id', 'owner_id',
        'concept', 'amount', 'currency', 'due_date', 'status', 'notes',
    ];

    protected $dates = ['due_date', 'paid_at', 'last_reminder_at'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'contact_id' => 'required|exists:aero_crm_contacts,id',
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
}
