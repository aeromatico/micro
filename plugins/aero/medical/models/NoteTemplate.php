<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\DefaultTemplates;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Plantilla editable de nota. Lo que ya se escribió con ella no cambia (cada nota guarda su foto). */
class NoteTemplate extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_note_templates';

    public $fillable = ['tenant_id', 'kind', 'name', 'sections', 'is_active'];

    public $jsonable = ['sections'];

    public $casts = ['is_active' => 'boolean'];

    public $rules = [
        'name' => 'required|string|max:255',
        'kind' => 'required|in:consultation,control',
    ];

    public function getKindOptions(): array
    {
        return DefaultTemplates::KINDS;
    }

    public function getTypeOptions(): array
    {
        return ['text' => 'Texto', 'vitals' => 'Signos vitales'];
    }

    public static function templateOptions(?int $tenantId, string $kind): array
    {
        return $tenantId
            ? static::forTenant($tenantId)->where('kind', $kind)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
            : [];
    }

    /** Siembra Consulta y Control por defecto si el negocio aún no tiene de ese tipo. */
    public static function ensureDefaults(int $tenantId): void
    {
        $mode = Mode::forTenant($tenantId);

        foreach (DefaultTemplates::KINDS as $kind => $label) {
            if (!static::where('tenant_id', $tenantId)->where('kind', $kind)->exists()) {
                static::create([
                    'tenant_id' => $tenantId, 'kind' => $kind, 'name' => $label,
                    'sections' => DefaultTemplates::sections($kind, $mode), 'is_active' => true,
                ]);
            }
        }
    }

    /** Plantilla con la que se abre cada nota: la elegida en Configuración o la primera activa. */
    public static function resolve(int $tenantId, string $kind): self
    {
        static::ensureDefaults($tenantId);

        $col = $kind === 'control' ? 'control_template_id' : 'consultation_template_id';
        $chosen = ClinicSettings::where('tenant_id', $tenantId)->value($col);

        $t = $chosen ? static::where('tenant_id', $tenantId)->where('kind', $kind)->where('is_active', true)->find($chosen) : null;

        return $t ?? static::where('tenant_id', $tenantId)->where('kind', $kind)->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function beforeValidate(): void
    {
        $out = [];
        $keys = [];
        $vitals = 0;

        foreach ((array) $this->sections as $s) {
            $label = trim((string) ($s['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = ($s['type'] ?? 'text') === 'vitals' ? 'vitals' : 'text';
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($s['key'] ?? ''))));
            $key = $key ?: ($type === 'vitals' ? 'vitals' : \Str::slug($label, '_'));
            $base = $key ?: 's';
            for ($i = 2; in_array($key, $keys, true); $i++) {
                $key = $base . '_' . $i;
            }
            $keys[] = $key;
            $vitals += $type === 'vitals' ? 1 : 0;

            $out[] = ['key' => $key, 'label' => $label, 'type' => $type, 'rows' => max(1, min(20, (int) ($s['rows'] ?? 3)))];
        }

        if (!$out) {
            throw new \ApplicationException('La plantilla necesita al menos una sección.');
        }
        if ($vitals > 1) {
            throw new \ApplicationException('Solo puede haber una sección de signos vitales.');
        }

        $this->sections = $out;
    }
}
