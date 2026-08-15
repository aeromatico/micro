<?php namespace Aero\Sites\Classes;

/**
 * Helpers de color en HSL para derivar una variante oscura de una paleta
 * pensada originalmente para superficies claras (ver DesignTheme::toCssVars).
 */
class ColorPalette
{
    /**
     * A partir de una paleta "light" (primary, primary_dark, secondary, accent,
     * neutral_bg, neutral_text), deriva una paleta "dark" completa con los 9
     * tokens de superficie. No es un motor de contraste real: usa umbrales
     * simples de luminosidad para mantener texto/fondo legibles.
     */
    public static function deriveDark(array $light): array
    {
        $primary = $light['primary'] ?? '#4f46e5';

        return [
            'primary'            => self::ensureLightEnough($primary, 45),
            'primary_dark'       => self::ensureLightEnough($light['primary_dark'] ?? $primary, 35),
            'secondary'          => self::ensureLightEnough($light['secondary'] ?? '#0ea5e9', 45),
            'accent'             => self::ensureLightEnough($light['accent'] ?? '#f59e0b', 50),
            'surface_bg'         => self::tintTowards('#0b0d12', $primary, 0.06),
            'surface_alt'        => self::tintTowards('#151821', $primary, 0.08),
            'surface_text'       => '#f1f5f9',
            'surface_text_muted' => '#94a3b8',
            'surface_border'     => 'rgba(255, 255, 255, 0.08)',
        ];
    }

    /**
     * Paleta "light" equivalente de superficie, a partir de los mismos 6
     * colores ya sembrados (neutral_bg/neutral_text ya son claros).
     */
    public static function deriveLightSurface(array $light): array
    {
        return [
            'primary'            => $light['primary'] ?? '#4f46e5',
            'primary_dark'       => $light['primary_dark'] ?? '#3730a3',
            'secondary'          => $light['secondary'] ?? '#0ea5e9',
            'accent'             => $light['accent'] ?? '#f59e0b',
            'surface_bg'         => $light['neutral_bg'] ?? '#f8fafc',
            'surface_alt'        => '#ffffff',
            'surface_text'       => $light['neutral_text'] ?? '#0f172a',
            'surface_text_muted' => self::mix($light['neutral_text'] ?? '#0f172a', '#ffffff', 0.45),
            'surface_border'     => self::mix($light['neutral_text'] ?? '#0f172a', '#ffffff', 0.88),
        ];
    }

    /** Sube la luminosidad de un color hex si está por debajo del mínimo dado (0-100). */
    protected static function ensureLightEnough(string $hex, int $minLightness): string
    {
        [$h, $s, $l] = self::hexToHsl($hex);
        if ($l >= $minLightness) {
            return $hex;
        }
        return self::hslToHex($h, $s, $minLightness);
    }

    /** Mezcla un color de base oscura con un tinte de marca a baja intensidad. */
    protected static function tintTowards(string $baseHex, string $tintHex, float $amount): string
    {
        return self::mix($baseHex, $tintHex, $amount);
    }

    /** Mezcla lineal en RGB entre dos colores hex ($amount = 0..1, hacia $to). */
    protected static function mix(string $from, string $to, float $amount): string
    {
        $a = self::hexToRgb($from);
        $b = self::hexToRgb($to);
        $amount = max(0.0, min(1.0, $amount));

        $r = (int) round($a[0] + ($b[0] - $a[0]) * $amount);
        $g = (int) round($a[1] + ($b[1] - $a[1]) * $amount);
        $bch = (int) round($a[2] + ($b[2] - $a[2]) * $amount);

        return self::rgbToHex($r, $g, $bch);
    }

    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected static function rgbToHex(int $r, int $g, int $b): string
    {
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    protected static function hexToHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($c) => $c / 255, self::hexToRgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, round($l * 100)];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };
        $h *= 60;

        return [round($h), round($s * 100), round($l * 100)];
    }

    protected static function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return self::rgbToHex((int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    protected static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }
}
