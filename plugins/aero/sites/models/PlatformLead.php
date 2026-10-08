<?php namespace Aero\Sites\Models;

use Model;

/**
 * Lead comercial del sitio de la plataforma (market.com.bo), no de un
 * micrositio de tenant — por eso vive aparte de ContactSubmission, que sí
 * requiere tenant_id. Lo llenan los botones "Trial" / "Gran Empresa" de la
 * sección de precios del theme master vía Components\PlatformLeadForm.
 */
class PlatformLead extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_platform_leads';

    public $fillable = [
        'plan', 'name', 'email', 'phone', 'message',
        'is_corporate_email', 'verification_url', 'metadata', 'status',
    ];

    protected $casts = [
        'metadata'            => 'array',
        'is_corporate_email'  => 'boolean',
    ];

    protected $dates = ['dispatched_at'];

    public $rules = [
        'plan'              => 'required|in:trial,enterprise',
        'name'              => 'required|min:2|max:100',
        'email'             => 'required|email|max:255',
        'phone'             => 'required|max:30',
        'message'           => 'required|min:5|max:2000',
        'verification_url'  => 'required_if:is_corporate_email,false|nullable|url|max:255',
    ];

    public $customMessages = [
        'verification_url.required_if' => 'Agrega el sitio web o red social de tu negocio.',
        'verification_url.url'         => 'Ingresa una URL válida (con http:// o https://).',
    ];

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    /** Solo marca que se intentó notificar al equipo; no cambia el status del lead. */
    public function markNotified(): void
    {
        $this->dispatched_at = now();
        $this->save();
    }
}
