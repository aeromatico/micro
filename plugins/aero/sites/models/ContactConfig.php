<?php namespace Aero\Sites\Models;

use Model;

class ContactConfig extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_contact_configs';

    public $fillable = [
        'tenant_id', 'contact_email', 'phone', 'whatsapp',
        'address', 'lat', 'lng', 'form_enabled', 'success_message',
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

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
