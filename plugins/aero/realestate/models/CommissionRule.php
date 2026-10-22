<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Cómo se calcula la comisión de una modalidad: % del monto (precio / capital), N meses de alquiler
 * (canon = el alquiler mensual) o un monto fijo. Una regla con agente sustituye a la general de esa modalidad para ese agente.
 */
class CommissionRule extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_commission_rules';

    public $fillable = ['tenant_id', 'modality', 'agent_id', 'basis', 'value', 'agent_share_percent', 'is_active'];

    public $rules = [
        'modality'            => 'required|in:sale,rent,anticretico',
        'basis'               => 'required|in:percent,monthly_rents,fixed',
        'value'               => 'required|numeric|min:0',
        'agent_share_percent' => 'required|numeric|min:0|max:100',
    ];

    public $attributes = ['basis' => 'percent', 'agent_share_percent' => 50, 'is_active' => true];

    public $belongsTo = ['agent' => [Agent::class, 'key' => 'agent_id']];

    public static array $bases = ['percent' => '% del monto (precio, alquiler mensual o capital)', 'monthly_rents' => 'Meses de alquiler (1 = un alquiler mensual)', 'fixed' => 'Monto fijo'];

    public function getModalityOptions(): array
    {
        return Offer::$modalities;
    }

    public function getBasisOptions(): array
    {
        return self::$bases;
    }

    public function getAgentIdOptions(): array
    {
        return Agent::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible(['agent_id' => Agent::class]);

        if ($this->basis === 'monthly_rents' && $this->modality !== 'rent') {
            throw new \ApplicationException('«Meses de alquiler» solo se puede usar en reglas de alquiler. Para ventas y anticréticos usa un porcentaje o un monto fijo.');
        }
    }

    /** La regla vigente para un agente y modalidad: la del agente si existe, si no la general. */
    public static function resolve(?int $tenantId, string $modality, ?int $agentId): ?self
    {
        $base = static::where('tenant_id', $tenantId)->where('modality', $modality)->where('is_active', true);

        return ($agentId ? (clone $base)->where('agent_id', $agentId)->first() : null) ?? $base->whereNull('agent_id')->first();
    }

    public function describe(): string
    {
        return match ($this->basis) {
            'percent'       => rtrim(rtrim(number_format((float) $this->value, 2, ',', ''), '0'), ',') . ' % del monto',
            'monthly_rents' => rtrim(rtrim(number_format((float) $this->value, 2, ',', ''), '0'), ',') . ' mes(es) de alquiler',
            default         => 'monto fijo ' . number_format((float) $this->value, 2, ',', '.'),
        };
    }
}
