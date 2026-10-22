<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Property;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda del portal: criterios sanitizados → consulta de propiedades publicadas con al menos una oferta
 * disponible. La usan el buscador, las búsquedas guardadas (alertas) y el comparador, así que sus
 * resultados coinciden. Los filtros de precio miran las OFERTAS: una propiedad con venta y anticrético
 * se encuentra por cualquiera de las dos, cada una con su monto.
 */
class PortalSearch
{
    public const MODALITIES = ['sale', 'rent', 'anticretico'];
    public const SORTS = ['recent', 'price_asc', 'price_desc', 'area_desc'];

    /** Solo claves conocidas y con tipo válido: lo demás se descarta (viene de la URL o de un formulario público). */
    public static function sanitize(array $in): array
    {
        $str = fn ($k, $max = 120) => ($v = trim((string) ($in[$k] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;
        $num = fn ($k) => (isset($in[$k]) && is_numeric($in[$k]) && $in[$k] >= 0) ? (float) $in[$k] : null;
        $int = fn ($k) => (isset($in[$k]) && is_numeric($in[$k]) && $in[$k] >= 0) ? (int) $in[$k] : null;

        $out = [
            'modality'    => in_array($in['modality'] ?? null, self::MODALITIES, true) ? $in['modality'] : null,
            'type'        => isset(Property::$types[$in['type'] ?? '']) ? $in['type'] : null,
            'department'  => $str('department', 64),
            'city'        => $str('city', 96),
            'zone'        => $str('zone', 96),
            'q'           => $str('q', 80),
            'currency'    => in_array($in['currency'] ?? null, ['BOB', 'USD'], true) ? $in['currency'] : null,
            'price_min'   => $num('price_min'), 'price_max' => $num('price_max'),
            'capital_min' => $num('capital_min'), 'capital_max' => $num('capital_max'),
            'term_min'    => $int('term_min'), 'term_max' => $int('term_max'),
            'area_min'    => $num('area_min'),
            'bedrooms'    => $int('bedrooms'), 'bathrooms' => $int('bathrooms'), 'garages' => $int('garages'),
            'features'    => array_values(array_unique(array_filter(array_map('intval', (array) ($in['features'] ?? [])), fn ($i) => $i > 0))),
            'sort'        => in_array($in['sort'] ?? null, self::SORTS, true) ? $in['sort'] : 'recent',
        ];

        return array_filter($out, fn ($v, $k) => $v !== null && $v !== [] && !($k === 'sort' && $v === 'recent'), ARRAY_FILTER_USE_BOTH);
    }

    public static function query(int $tenantId, array $f = []): Builder
    {
        $q = Property::query()->where('tenant_id', $tenantId)->published()
            ->whereHas('offers', fn ($o) => self::offerConstraints($o, $f))
            ->with(['offers' => fn ($o) => $o->where('status', 'available')->orderBy('modality'), 'photos', 'agent']);

        foreach (['type', 'department', 'city', 'zone'] as $col) {
            if (!empty($f[$col])) {
                $q->where($col, $f[$col]);
            }
        }
        if (!empty($f['q'])) {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('address', 'like', $like)
                ->orWhere('neighborhood', 'like', $like)->orWhere('zone', 'like', $like)->orWhere('code', 'like', $like));
        }
        if (!empty($f['area_min'])) {
            $q->where(fn ($w) => $w->where('built_area', '>=', $f['area_min'])->orWhere('land_area', '>=', $f['area_min']));
        }
        foreach (['bedrooms', 'bathrooms', 'garages'] as $col) {
            if (!empty($f[$col])) {
                $q->where($col, '>=', $f[$col]);
            }
        }
        foreach ($f['features'] ?? [] as $featureId) {
            $q->whereHas('features', fn ($w) => $w->where('aero_realestate_features.id', $featureId));
        }

        $q->withMin(['offers as min_amount' => fn ($o) => self::offerConstraints($o, $f)], 'amount');

        return match ($f['sort'] ?? 'recent') {
            'price_asc'  => $q->orderBy('min_amount'),
            'price_desc' => $q->orderByDesc('min_amount'),
            'area_desc'  => $q->orderByRaw('COALESCE(built_area, land_area, 0) DESC'),
            default      => $q->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /** Las ofertas que cuentan: disponibles, de la modalidad pedida y dentro de los rangos (precio o capital/plazo). */
    public static function offerConstraints($o, array $f)
    {
        $o->where('status', 'available');
        $modality = $f['modality'] ?? null;
        if ($modality) {
            $o->where('modality', $modality);
        }
        if (!empty($f['currency'])) {
            $o->where('currency', $f['currency']);
        }

        [$min, $max] = $modality === 'anticretico' ? [$f['capital_min'] ?? null, $f['capital_max'] ?? null] : [$f['price_min'] ?? null, $f['price_max'] ?? null];
        $min !== null && $o->where('amount', '>=', $min);
        $max !== null && $o->where('amount', '<=', $max);

        if ($modality === 'anticretico') {
            isset($f['term_min']) && $o->where('term_months', '>=', $f['term_min']);
            isset($f['term_max']) && $o->where('term_months', '<=', $f['term_max']);
        }

        return $o;
    }

    /** Resumen legible de los criterios, para nombrar y mostrar una búsqueda guardada. */
    public static function describe(array $f): string
    {
        $parts = [];
        $parts[] = \Aero\Realestate\Models\Offer::$modalities[$f['modality'] ?? ''] ?? 'Todas las modalidades';
        isset($f['type']) && $parts[] = Property::$types[$f['type']];
        $place = array_filter([$f['zone'] ?? null, $f['city'] ?? null, $f['department'] ?? null]);
        $place && $parts[] = implode(', ', $place);
        $cur = $f['currency'] ?? '';
        $a = ($f['modality'] ?? null) === 'anticretico' ? [$f['capital_min'] ?? null, $f['capital_max'] ?? null] : [$f['price_min'] ?? null, $f['price_max'] ?? null];
        if ($a[0] !== null || $a[1] !== null) {
            $parts[] = trim(($a[0] !== null ? 'desde ' . number_format($a[0], 0, ',', '.') : '') . ' ' . ($a[1] !== null ? 'hasta ' . number_format($a[1], 0, ',', '.') : '') . ' ' . $cur);
        }
        !empty($f['bedrooms']) && $parts[] = $f['bedrooms'] . '+ hab.';

        return implode(' · ', $parts);
    }
}
