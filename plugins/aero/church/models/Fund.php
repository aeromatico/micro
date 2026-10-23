<?php namespace Aero\Church\Models;

use Model;

/** Concepto de aporte: diezmo, ofrenda, donación o campaña (con meta opcional). */
class Fund extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const KINDS = ['tithe' => 'Diezmo', 'offering' => 'Ofrenda', 'donation' => 'Donación', 'campaign' => 'Campaña'];

    public $table = 'aero_church_funds';

    public $attributes = ['kind' => 'offering', 'is_active' => true];

    public $fillable = ['tenant_id', 'name', 'kind', 'goal_amount', 'starts_on', 'ends_on', 'is_active'];

    public $nullable = ['goal_amount', 'starts_on', 'ends_on'];

    public $rules = [
        'name'        => 'required|string|max:255',
        'kind'        => 'required|in:tithe,offering,donation,campaign',
        'goal_amount' => 'nullable|numeric|min:0',
        'ends_on'     => 'nullable|date|after_or_equal:starts_on',
    ];

    public $dates = ['starts_on', 'ends_on'];

    public $hasMany = [
        'contributions' => [Contribution::class, 'key' => 'fund_id'],
    ];

    public function getKindOptions(): array
    {
        return self::KINDS;
    }

    /** Lo recaudado (solo aportes confirmados). */
    public function getRaisedAttribute(): float
    {
        return (float) $this->contributions()->where('status', 'confirmed')->sum('amount');
    }

    /** Conceptos que hoy admiten aportes. */
    public function scopeOpen($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()));
    }

    /** Siembra los conceptos básicos de una iglesia (idempotente). */
    public static function seedDefaults(int $tenantId): void
    {
        foreach (['Diezmo' => 'tithe', 'Ofrenda' => 'offering', 'Donación' => 'donation'] as $name => $kind) {
            self::firstOrCreate(['tenant_id' => $tenantId, 'name' => $name], ['kind' => $kind, 'is_active' => true]);
        }
    }
}
