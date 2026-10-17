<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Range;

/** Marca «low»/«high» según los rangos del negocio; el más específico (especie) gana. */
class Ranges
{
    protected static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** @return 'low'|'high'|null */
    public static function flag(int $tenantId, ?string $mode, ?string $species, string $metric, $value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $mode = Mode::valid($mode) ? $mode : Mode::HUMAN;

        $all = self::$cache[$tenantId][$mode] ??= Range::where('tenant_id', $tenantId)->where('mode', $mode)->get()->groupBy('metric');
        $cands = $all[$metric] ?? collect();
        if ($cands->isEmpty()) {
            return null;
        }

        $r = $cands->first(fn ($x) => $species && $x->species === $species) ?? $cands->first(fn ($x) => $x->species === null);
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
