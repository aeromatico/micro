<?php namespace Aero\Crm\Models;

use Model;

/**
 * Un paso de la cascada de recordatorios de cobranza: "cuántos días antes o
 * después del vencimiento enviar qué mensaje, a qué lista". Varias reglas
 * activas para la misma lista (o sin lista = todos los cobros) forman una
 * secuencia de dunning (ej. -3, 0, +5, +15 días). Ver
 * Classes\Collections\CollectionReminderGenerator para la lógica de disparo.
 */
class CollectionReminderRule extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_collection_reminder_rules';

    public $fillable = [
        'tenant_id', 'contact_list_id', 'name', 'offset_days',
        'message_template', 'active', 'sort_order',
    ];

    public $rules = [
        'tenant_id'   => 'required|exists:aero_sites_tenants,id',
        'name'        => 'required|max:255',
        'offset_days' => 'required|integer|between:-365,365',
    ];

    public $belongsTo = [
        'tenant'      => [\Aero\Sites\Models\Tenant::class],
        'contactList' => [ContactList::class],
    ];

    public $hasMany = [
        'logs' => [CollectionReminderLog::class],
    ];

    public $attributes = [
        'active'      => true,
        'offset_days' => 0,
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

    public function getOffsetLabelAttribute(): string
    {
        if ($this->offset_days === 0) {
            return 'El día del vencimiento';
        }

        return $this->offset_days < 0
            ? abs($this->offset_days) . ' día(s) antes del vencimiento'
            : $this->offset_days . ' día(s) después del vencimiento (vencido)';
    }
}
