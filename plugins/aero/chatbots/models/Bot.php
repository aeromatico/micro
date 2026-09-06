<?php namespace Aero\Chatbots\Models;

use Aero\Hello\Models\Account;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Model;

/**
 * Un chatbot de reglas simples atado a una única cuenta de Aero.Hello
 * (número/perfil social). Un tenant puede tener varios bots si tiene varias
 * cuentas conectadas.
 */
class Bot extends Model
{
    use \October\Rain\Database\Traits\Validation;
    // Los <select> de un FormField solo consultan métodos del modelo (ver
    // FormField::getOptionsFromModelAsDefault), no del controller, así que
    // el filtrado por tenant vive aquí en vez de en Bots.
    use ResolvesCurrentTenant;

    public $table = 'aero_chatbots_bots';

    public $fillable = [
        'tenant_id', 'account_id', 'name', 'is_active', 'fallback_message', 'handoff_minutes',
    ];

    public $rules = [
        'account_id'      => 'required|unique:aero_chatbots_bots',
        'name'            => 'required|max:255',
        'handoff_minutes' => 'required|integer|min:0|max:1440',
    ];

    public $belongsTo = [
        'account' => [\Aero\Hello\Models\Account::class],
        'tenant'  => [\Aero\Sites\Models\Tenant::class],
    ];

    public $hasMany = [
        'rules' => [Rule::class, 'delete' => true],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Cuentas de Hello disponibles para el <select> del form: si hay un
     * tenant en contexto, solo las suyas (vía su Profile); el superadmin sin
     * tenant propio ve todas.
     */
    public function getAccountIdOptions(): array
    {
        $query = Account::orderBy('label');

        if ($tenantId = $this->getCurrentTenantId()) {
            $query->whereHas('profile', fn ($q) => $q->where('tenant_id', $tenantId));
        }

        return $query->get()
            ->mapWithKeys(fn ($account) => [$account->id => "{$account->label} ({$account->platform})"])
            ->all();
    }

    public function getTenantIdOptions(): array
    {
        return Tenant::orderBy('name')->pluck('name', 'id')->all();
    }
}
