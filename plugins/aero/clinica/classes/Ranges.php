<?php namespace Aero\Clinica\Classes;

use Aero\Clinica\Models\Range;

/** Marca «low»/«high» según los rangos del negocio; por sexo si el rango lo define. */
class Ranges
{
    protected static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** @return 'low'|'high'|null */
    public static function flag(int $tenantId, ?string $sex, string $metric, $value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $all = self::$cache[$tenantId] ??= Range::where('tenant_id', $tenantId)->get()->groupBy('metric');
        $cands = $all[$metric] ?? collect();
        if ($cands->isEmpty()) {
            return null;
        }

        $r = $cands->first(fn ($x) => $sex && $x->sex === $sex) ?? $cands->first(fn ($x) => $x->sex === null);
        if (!$r) {
            return null;
        }

        if ($r->low !== null && (float) $value < (float) $r->low) {
            return 'low';
        }
        if ($r->high !== null && (float) $value > (float) $r->high) {
            return 'high';
        }

        return null;
    }
}
