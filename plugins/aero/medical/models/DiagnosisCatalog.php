<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/**
 * Diagnósticos del negocio: los «Frecuentes» (CIE-10 fijados o más usados) y el
 * catálogo propio (texto con código opcional). En modo animal es el catálogo
 * principal, porque CIE-10 no aplica.
 */
class DiagnosisCatalog extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_diagnosis_catalog';

    public $fillable = ['tenant_id', 'source', 'code', 'label', 'pinned'];

    public $casts = ['pinned' => 'boolean'];

    protected $dates = ['last_used_at'];

    public $rules = [
        'label' => 'required|string|max:500',
    ];

    public function getSourceOptions(): array
    {
        return ['custom' => 'Propio del negocio', 'cie10' => 'CIE-10'];
    }

    public function beforeValidate(): void
    {
        $this->source = $this->source ?: 'custom';
        $this->code = $this->code ? strtoupper(trim($this->code)) : null;
    }

    public function beforeSave(): void
    {
        $this->entry_key = $this->code ?: md5(mb_strtolower(trim((string) $this->label)));
    }

    /** Frecuentes del negocio: fijados primero, luego los más usados. */
    public static function frequent(int $tenantId, ?string $mode, int $limit = 12)
    {
        $q = static::forTenant($tenantId);
        if ($mode === 'animal') {
            $q->where('source', 'custom');
        }

        return $q->orderByDesc('pinned')->orderByDesc('uses')->orderBy('label')->limit($limit)->get();
    }

    public static function bump(int $tenantId, string $source, ?string $code, string $label): void
    {
        if (!in_array($source, ['cie10', 'custom'], true)) {
            return; // el texto libre no se acumula
        }

        $key = $code ?: md5(mb_strtolower(trim($label)));
        $row = static::firstOrNew(['tenant_id' => $tenantId, 'source' => $source, 'entry_key' => $key]);
        $row->code = $code;
        $row->label = $row->label ?: $label;
        $row->uses = (int) $row->uses + 1;
        $row->last_used_at = now();
        $row->save();
    }
}
