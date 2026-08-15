<?php namespace Aero\Sites\Models;

use Aero\Sites\Classes\ColorPalette;
use Aero\Sites\Classes\Niches\NicheManager;
use Model;

/**
 * Catálogo curado de temas visuales (paleta, tipografía, radio de esquinas).
 * La IA elige un handle de este catálogo cerrado — nunca inventa colores.
 */
class DesignTheme extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_design_themes';

    public $fillable = [
        'handle', 'name', 'tone', 'niche_affinity', 'colors',
        'font_heading', 'font_heading2', 'font_body', 'radius', 'enable_animations', 'is_active',
    ];

    protected $jsonable = ['niche_affinity', 'colors'];

    public $rules = [
        'handle' => 'required|alpha_dash|unique:aero_sites_design_themes,handle',
        'name'   => 'required',
        'tone'   => 'required',
    ];

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

    public function getNicheAffinityOptions(): array
    {
        return app(NicheManager::class)->options();
    }

    /**
     * Paleta cruda { light: {...9 tokens...}, dark: {...9 tokens...} }, con
     * fallback para registros aún no migrados a la forma dual (ver
     * updates/add_dual_palette_to_design_themes.php): si `colors` todavía
     * trae la forma plana pre-1.12 (primary/neutral_bg/...), deriva ambas
     * variantes al vuelo con ColorPalette.
     */
    public function getDualColors(): array
    {
        $colors = $this->colors ?? [];

        if (isset($colors['light'], $colors['dark'])) {
            return $colors;
        }

        return [
            'light' => ColorPalette::deriveLightSurface($colors),
            'dark'  => ColorPalette::deriveDark($colors),
        ];
    }

    /**
     * Variables CSS para ambos modos, listas para inyectar en `:root` (light)
     * y `:root.dark` (dark). $overrides['colors'] se aplica a ambas variantes
     * por igual (personalización simple desde el panel del tenant); se puede
     * pasar $overrides['colors']['light'|'dark'] para overrides específicos.
     * $overrides['fonts'] (heading/heading2/body) no varía por modo, igual
     * que las fuentes del tema base.
     */
    public function toCssVars(array $overrides = []): array
    {
        $dual = $this->getDualColors();
        $flatOverride = array_diff_key($overrides['colors'] ?? [], array_flip(['light', 'dark']));

        $radiusMap = ['sharp' => '0.125rem', 'soft' => '0.75rem', 'round' => '1.5rem'];
        $radius = $overrides['radius'] ?? $this->radius;

        $fontOverride = $overrides['fonts'] ?? [];

        $shared = [
            '--font-heading'   => $fontOverride['heading'] ?? $this->font_heading ?? 'Inter',
            '--font-heading-2' => $fontOverride['heading2'] ?? $this->font_heading2 ?? $this->font_heading ?? 'Inter',
            '--font-body'      => $fontOverride['body'] ?? $this->font_body ?? 'Inter',
            '--radius'         => $radiusMap[$radius] ?? '0.75rem',
        ];

        $build = function (string $mode) use ($dual, $flatOverride, $overrides) {
            $colors = array_merge($dual[$mode] ?? [], $flatOverride, $overrides['colors'][$mode] ?? []);

            return [
                '--color-primary'        => $colors['primary'] ?? '#4f46e5',
                '--color-primary-dark'   => $colors['primary_dark'] ?? '#3730a3',
                '--color-secondary'      => $colors['secondary'] ?? '#0ea5e9',
                '--color-accent'         => $colors['accent'] ?? '#f59e0b',
                '--color-surface-bg'     => $colors['surface_bg'] ?? '#f8fafc',
                '--color-surface-alt'    => $colors['surface_alt'] ?? '#ffffff',
                '--color-surface-text'   => $colors['surface_text'] ?? '#0f172a',
                '--color-surface-text-muted' => $colors['surface_text_muted'] ?? '#64748b',
                '--color-surface-border' => $colors['surface_border'] ?? '#e2e8f0',
                // Alias legacy — algunas plantillas/páginas guardadas en BD
                // aún referencian --color-neutral-bg/text directamente.
                '--color-neutral-bg'     => $colors['surface_bg'] ?? '#f8fafc',
                '--color-neutral-text'   => $colors['surface_text'] ?? '#0f172a',
            ];
        };

        return [
            'light' => array_merge($build('light'), $shared),
            'dark'  => array_merge($build('dark'), $shared),
        ];
    }
}
