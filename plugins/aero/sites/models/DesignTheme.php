<?php namespace Aero\Sites\Models;

use Model;

/**
 * Catálogo curado de temas visuales (paleta, tipografía, radio de esquinas).
 * La IA elige un handle de este catálogo cerrado — nunca inventa colores.
 */
class DesignTheme extends Model
{
    public $table = 'aero_sites_design_themes';

    public $fillable = [
        'handle', 'name', 'tone', 'niche_affinity', 'colors',
        'font_heading', 'font_body', 'radius', 'is_active',
    ];

    protected $jsonable = ['niche_affinity', 'colors'];

    public $hasMany = [
        'tenants' => [Tenant::class],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForNiche($query, string $nicheType)
    {
        return $query->where(function ($q) use ($nicheType) {
            $q->whereJsonContains('niche_affinity', $nicheType)
              ->orWhereNull('niche_affinity')
              ->orWhereJsonLength('niche_affinity', 0);
        });
    }

    public function scopeOfTone($query, string $tone)
    {
        return $query->where('tone', $tone);
    }

    /**
     * Variables CSS listas para inyectar en un <style>:root scoped.
     */
    public function toCssVars(array $overrides = []): array
    {
        $colors = array_merge($this->colors ?? [], $overrides['colors'] ?? []);
        $radiusMap = ['sharp' => '0.125rem', 'soft' => '0.75rem', 'round' => '1.5rem'];
        $radius = $overrides['radius'] ?? $this->radius;

        return [
            '--color-primary'      => $colors['primary'] ?? '#4f46e5',
            '--color-primary-dark' => $colors['primary_dark'] ?? '#3730a3',
            '--color-secondary'    => $colors['secondary'] ?? '#0ea5e9',
            '--color-accent'       => $colors['accent'] ?? '#f59e0b',
            '--color-neutral-bg'   => $colors['neutral_bg'] ?? '#f8fafc',
            '--color-neutral-text' => $colors['neutral_text'] ?? '#0f172a',
            '--font-heading'       => $this->font_heading ?? 'Inter',
            '--font-body'          => $this->font_body ?? 'Inter',
            '--radius'             => $radiusMap[$radius] ?? '0.75rem',
        ];
    }
}
