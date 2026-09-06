<?php namespace Aero\Crm\Models;

use Model;

/**
 * Configuración de recordatorios automáticos para una lista de cobros.
 */
class CollectionReminderRule extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_collection_reminder_rules';

    public $fillable = [
        'tenant_id', 'contact_list_id', 'name', 'offset_days',
        'start_days_before', 'frequency_days',
        'message_template', 'active', 'sort_order',
    ];

    public $rules = [
        'tenant_id'   => 'required|exists:aero_sites_tenants,id',
        'start_days_before' => 'required|integer|between:1,30',
        'frequency_days'    => 'required|integer|in:1,2,3,5,7',
    ];

    public $belongsTo = [
        'tenant'      => [\Aero\Sites\Models\Tenant::class],
        'contactList' => [ContactList::class],
    ];

    public $hasMany = [
        'logs' => [CollectionReminderLog::class],
    ];

    public $attributes = [
        'active'            => true,
        'offset_days'       => null,
        'start_days_before' => 5,
        'frequency_days'    => 2,
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function getContactListIdOptions(): array
    {
        return ['' => 'Todas las listas / todos los cobros'] + ContactList::orderBy('name')->pluck('name', 'id')->all();
    }


    public function getFrequencyDaysOptions(): array
    {
        return [
            1 => 'Cada 1 día', 2 => 'Cada 2 días', 3 => 'Cada 3 días',
            5 => 'Cada 5 días', 7 => 'Cada 7 días',
        ];
    }

    public function getOffsetLabelAttribute(): string
    {
        return sprintf('%d días antes, cada %d días', $this->start_days_before, $this->frequency_days);
    }
}
