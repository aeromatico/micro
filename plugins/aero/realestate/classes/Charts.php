<?php namespace Aero\Realestate\Classes;

/**
 * Gráficos mínimos en SVG en línea: sin librerías externas (el panel no depende de un CDN), con texto alternativo
 * y valores visibles. Los colores usan currentColor / variables para respetar el tema del panel.
 */
class Charts
{
    /** Barras horizontales: etiqueta → valor. */
    public static function hbars(array $rows, string $color = '#3498db', string $unit = ''): string
    {
        $rows = array_values($rows);
        if (!$rows) {
            return '<p class="text-muted">Sin datos en el periodo.</p>';
        }
        $max = max(1, ...array_map(fn ($r) => (float) $r['n'], array_values($rows)));
        $out = '<div role="list" style="display:grid;gap:6px">';
        foreach ($rows as $r) {
            $pct = round((float) $r['n'] / $max * 100, 1);
            $out .= '<div role="listitem" style="display:grid;grid-template-columns:minmax(80px,30%) 1fr 56px;align-items:center;gap:8px;font-size:12px">'
                . '<span title="' . e($r['k']) . '" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . e($r['k']) . '</span>'
                . '<span style="background:rgba(127,127,127,.15);border-radius:3px;height:14px;display:block"><span style="display:block;height:14px;border-radius:3px;background:' . e($color) . ';width:' . $pct . '%"></span></span>'
                . '<strong style="text-align:right">' . e((string) $r['n']) . e($unit) . '</strong></div>';
        }

        return $out . '</div>';
    }

    /**
     * Columnas agrupadas por mes. $series: [nombre => [valores…]] con el mismo largo que $labels.
     * Cada serie lleva su color; la leyenda y los valores quedan como texto accesible.
     */
    public static function columns(array $labels, array $series, array $colors): string
    {
        $n = count($labels);
        $max = max(1, ...array_values(array_map(fn ($v) => max($v ?: [0]), $series)));
        $w = 520;
        $h = 170;
        $pad = 26;
        $group = ($w - 2 * $pad) / max(1, $n);
        $bw = $group / (count($series) + 1);

        $svg = '<svg viewBox="0 0 ' . $w . ' ' . ($h + 34) . '" role="img" aria-label="' . e('Gráfico mensual: ' . implode(', ', array_keys($series))) . '" style="width:100%;max-width:640px;height:auto">';
        for ($g = 0; $g <= 4; $g++) {
            $y = $pad + ($h - $pad) * $g / 4;
            $svg .= '<line x1="' . $pad . '" x2="' . ($w - $pad) . '" y1="' . $y . '" y2="' . $y . '" stroke="currentColor" opacity=".12"/>';
        }
        foreach ($labels as $i => $label) {
            $x0 = $pad + $group * $i + $bw / 2;
            $si = 0;
            foreach ($series as $name => $vals) {
                $v = (float) ($vals[$i] ?? 0);
                $bh = ($h - $pad) * $v / $max;
                $x = $x0 + $bw * $si;
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw - 3, 1) . '" height="' . round($bh, 1) . '" rx="2" fill="' . e($colors[$si] ?? '#999') . '"><title>' . e($label . ' · ' . $name . ': ' . $v) . '</title></rect>';
                $v > 0 && $svg .= '<text x="' . round($x + ($bw - 3) / 2, 1) . '" y="' . round($h - $bh - 3, 1) . '" font-size="9" text-anchor="middle" fill="currentColor">' . (int) $v . '</text>';
                $si++;
            }
            $svg .= '<text x="' . round($pad + $group * ($i + .5), 1) . '" y="' . ($h + 16) . '" font-size="11" text-anchor="middle" fill="currentColor" opacity=".75">' . e($label) . '</text>';
        }
        $svg .= '</svg>';

        $legend = '<div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;margin-top:4px">';
        $si = 0;
        foreach (array_keys($series) as $name) {
            $legend .= '<span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:' . e($colors[$si] ?? '#999') . ';margin-right:5px"></span>' . e($name) . '</span>';
            $si++;
        }

        return $svg . $legend . '</div>';
    }

    /** Barra apilada de un total repartido: [etiqueta => [n, color]]. */
    public static function stacked(array $parts): string
    {
        $total = array_sum(array_map(fn ($p) => $p[0], $parts));
        if ($total <= 0) {
            return '<p class="text-muted">Sin datos.</p>';
        }
        $bar = '<div style="display:flex;height:18px;border-radius:4px;overflow:hidden" role="img" aria-label="' . e(implode(', ', array_map(fn ($k, $p) => "$k: $p[0]", array_keys($parts), $parts))) . '">';
        $legend = '<div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;margin-top:6px">';
        foreach ($parts as $label => [$n, $color]) {
            if ($n > 0) {
                $bar .= '<span title="' . e("$label: $n") . '" style="width:' . round($n / $total * 100, 2) . '%;background:' . e($color) . '"></span>';
            }
            $legend .= '<span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:' . e($color) . ';margin-right:5px"></span>' . e($label) . ' <strong>' . $n . '</strong></span>';
        }

        return $bar . '</div>' . $legend . '</div>';
    }
}
