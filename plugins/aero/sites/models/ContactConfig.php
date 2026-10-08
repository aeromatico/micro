<?php namespace Aero\Sites\Models;

use Model;

class ContactConfig extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_contact_configs';

    public $fillable = [
        'tenant_id', 'contact_email', 'phone', 'whatsapp',
        'contact_enabled', 'no_physical_address', 'city', 'region', 'address', 'lat', 'lng',
        'form_enabled', 'success_message',
    ];

    /** Por ahora solo Bolivia: el país es fijo y los departamentos son la región. */
    public const COUNTRY_CODE = 'BO';

    public const BO_DEPARTMENTS = [
        'chuquisaca' => 'Chuquisaca',
        'la_paz'     => 'La Paz',
        'cochabamba' => 'Cochabamba',
        'oruro'      => 'Oruro',
        'potosi'     => 'Potosí',
        'tarija'     => 'Tarija',
        'santa_cruz' => 'Santa Cruz',
        'beni'       => 'Beni',
        'pando'      => 'Pando',
    ];

    public $rules = [
        'tenant_id'     => 'required|exists:aero_sites_tenants,id',
        'contact_email' => 'nullable|email',
        'lat'           => 'nullable|numeric|between:-90,90',
        'lng'           => 'nullable|numeric|between:-180,180',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public function getWhatsappLinkAttribute(): ?string
    {
        if (!$this->whatsapp) return null;
        $number = preg_replace('/[^0-9]/', '', $this->whatsapp);
        return "https://wa.me/{$number}";
    }

    /** Sin fila todavía, el área de contacto está activa (mismo comportamiento de siempre). */
    public static function isEnabledFor(int $tenantId): bool
    {
        $row = static::where('tenant_id', $tenantId)->first(['contact_enabled']);

        return $row ? (bool) $row->contact_enabled : true;
    }

    /** Negocio sin local: no hay dirección ni mapa que mostrar. */
    public function hasPhysicalAddress(): bool
    {
        return !$this->no_physical_address;
    }

    public function hasLocation(): bool
    {
        return $this->hasPhysicalAddress() && $this->lat !== null && $this->lng !== null;
    }

    /** "Ciudad, Departamento" para mostrar; null si no hay ninguno. */
    public function getCityRegionLabelAttribute(): ?string
    {
        $region = self::BO_DEPARTMENTS[$this->region] ?? null;
        $parts = array_filter([$this->city, $region]);

        return $parts ? implode(', ', $parts) : null;
    }
}
