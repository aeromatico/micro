<?php namespace Aero\School\Classes;

/** Monto en letras para recibos: 1.250,50 → «Mil doscientos cincuenta 50/100 bolivianos». */
class AmountInWords
{
    private const UNITS = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce',
        'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro',
        'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
    private const TENS = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];
    private const HUNDREDS = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos',
        6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];

    public static function money(float $amount, string $currency = 'BOB'): string
    {
        $amount = round($amount, 2);
        $int = (int) floor($amount);
        $cents = (int) round(($amount - $int) * 100);
        $name = ['BOB' => 'bolivianos', 'USD' => 'dólares americanos'][$currency] ?? $currency;

        return ucfirst(self::number($int)) . ' ' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT) . '/100 ' . $name;
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        if ($n >= 1000000) {
            $m = intdiv($n, 1000000);
            $rest = $n % 1000000;

            return ($m === 1 ? 'un millón' : self::number($m) . ' millones') . ($rest ? ' ' . self::number($rest) : '');
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            $rest = $n % 1000;

            return ($k === 1 ? 'mil' : self::below1000($k, true) . ' mil') . ($rest ? ' ' . self::below1000($rest) : '');
        }

        return self::below1000($n);
    }

    private static function below1000(int $n, bool $apocope = false): string
    {
        if ($n === 100) {
            return 'cien';
        }
        $out = [];
        if ($n >= 100) {
            $out[] = self::HUNDREDS[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n > 0) {
            if ($n < 30) {
                $w = self::UNITS[$n];
            } else {
                $w = self::TENS[intdiv($n, 10)] . ($n % 10 ? ' y ' . self::UNITS[$n % 10] : '');
            }
            if ($apocope) {
                $w = preg_replace(['/veintiuno$/', '/uno$/'], ['veintiún', 'un'], $w);
            }
            $out[] = $w;
        }

        return implode(' ', $out);
    }
}
