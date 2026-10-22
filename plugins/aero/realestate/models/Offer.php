<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Oferta de un inmueble en una modalidad. Una propiedad puede tener varias
 * (venta + anticrético…) y cada una se administra por separado.
 * amount = precio (venta), canon mensual (alquiler) o capital (anticrético).
 */
class Offer extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_offers';

    public $fillable = ['tenant_id', 'property_id', 'modality', 'amount', 'currency', 'term_months', 'deposit', 'conditions', 'status', 'closed_at'];

    public $rules = [
        'modality' => 'required|in:sale,rent,anticretico',
        'amount'   => 'required|numeric|min:0',
        'currency' => 'required|in:BOB,USD',
        'status'   => 'required|in:available,reserved,closed,inactive',
    ];

    public $attributes = ['status' => 'available', 'currency' => 'USD'];

    public $dates = ['closed_at'];

    public $belongsTo = ['property' => [Property::class, 'key' => 'property_id']];

    public static array $modalities = ['sale' => 'Venta', 'rent' => 'Alquiler', 'anticretico' => 'Anticrético'];
    public static array $statuses = ['available' => 'Disponible', 'reserved' => 'Reservada', 'closed' => 'Cerrada', 'inactive' => 'Inactiva'];

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function getModalityOptions(): array
    {
        return self::$modalities;
    }

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function getCurrencyOptions(): array
    {
        return ['USD' => 'USD (dólares)', 'BOB' => 'BOB (bolivianos)'];
    }

    public function getPropertyIdOptions(): array
    {
        return Property::visible()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function getModalityNameAttribute(): string
    {
        return self::$modalities[$this->modality] ?? (string) $this->modality;
    }

    public function beforeValidate(): void
    {
        // Desde la relación del inmueble el tenant siempre es el de la propiedad.
        if ($this->property_id && ($tenant = Property::whereKey($this->property_id)->value('tenant_id'))) {
            $this->tenant_id = $tenant;
        }
    }

    /** Texto público: «USD 90.000», «USD 500 / mes» o «Capital USD 25.000 · 24 meses». */
    public function getLabelAttribute(): string
    {
        $money = $this->currency . ' ' . number_format((float) $this->amount, 0, ',', '.');

        return match ($this->modality) {
            'rent'        => $money . ' / mes',
            'anticretico' => 'Capital ' . $money . ($this->term_months ? ' · ' . $this->term_months . ' meses' : ''),
            default       => $money,
        };
    }

    protected ?array $priceChange = null;

    public function beforeSave(): void
    {
        $this->priceChange = ($this->exists && $this->isDirty('amount') && $this->getOriginal('amount') !== null)
            ? ['old' => (float) $this->getOriginal('amount'), 'new' => (float) $this->amount] : null;

        $this->assertReferencesVisible(['property_id' => Property::class]);

        if ($this->modality === 'sale') {
            $this->term_months = null;
        }
        if ($this->status === 'closed' && !$this->closed_at) {
            $this->closed_at = now();
        }
        if ($this->status !== 'closed') {
            $this->closed_at = null;
        }
    }

    public function afterUpdate(): void
    {
        if ($this->priceChange && $this->status === 'available' && Property::whereKey($this->property_id)->where('is_published', true)->exists()) {
            Change::create([
                'tenant_id' => $this->tenant_id, 'property_id' => $this->property_id, 'offer_id' => $this->id, 'kind' => 'price',
                'old_value' => (string) $this->priceChange['old'], 'new_value' => (string) $this->priceChange['new'],
                'currency' => $this->currency, 'created_at' => now(),
            ]);
        }
        $this->priceChange = null;
    }
}
