<?php namespace Aero\Pos\Models;

use Model;

class PosSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_pos_settings';

    public $fillable = [
        'tenant_id', 'profile', 'feat_tables', 'feat_tabs', 'feat_kitchen', 'feat_tips', 'feat_barcode',
        'feat_free_amount', 'tip_presets', 'discount_limit_percent', 'require_shift', 'ticket_width',
        'ticket_header', 'ticket_footer',
    ];

    public $rules = [
        'tenant_id'              => 'required|exists:aero_sites_tenants,id',
        'profile'                => 'required|in:restaurant,retail,quick',
        'discount_limit_percent' => 'nullable|integer|min:0|max:100',
        'ticket_width'           => 'in:58,80',
    ];

    public const PROFILES = [
        'restaurant' => 'Restaurante',
        'retail'     => 'Tienda / comercio',
        'quick'      => 'Cobro rápido (servicios)',
    ];

    /** Módulos que activa cada perfil. */
    public const PRESETS = [
        'restaurant' => ['feat_tables' => true,  'feat_tabs' => true,  'feat_kitchen' => true,  'feat_tips' => true,  'feat_barcode' => false, 'feat_free_amount' => false],
        'retail'     => ['feat_tables' => false, 'feat_tabs' => false, 'feat_kitchen' => false, 'feat_tips' => false, 'feat_barcode' => true,  'feat_free_amount' => true],
        'quick'      => ['feat_tables' => false, 'feat_tabs' => false, 'feat_kitchen' => false, 'feat_tips' => true,  'feat_barcode' => false, 'feat_free_amount' => true],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Propinas sugeridas como lista de porcentajes: «0,5,10» → [0, 5, 10]. */
    public function tipPercents(): array
    {
        $out = [];
        foreach (explode(',', (string) $this->tip_presets) as $p) {
            if (is_numeric(trim($p))) {
                $out[] = max(0, min(100, (float) trim($p)));
            }
        }

        return $out ?: [0, 5, 10];
    }

    public function applyPreset(string $profile): void
    {
        $this->profile = $profile;
        $this->fill(self::PRESETS[$profile] ?? []);
    }
}
