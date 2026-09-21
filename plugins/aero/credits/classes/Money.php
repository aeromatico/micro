<?php namespace Aero\Credits\Classes;

/**
 * Dinero en la billetera de Bs. Se guarda en diezmilésimas de boliviano
 * (1 Bs = 10.000 unidades) porque los precios por moneda tienen 4 decimales:
 * con esta unidad cost = monedas × precio es SIEMPRE un entero exacto, así que
 * ni una recarga ni una compra pierden un solo céntimo por redondeo.
 *
 * Lo que VE el tenant va en 2 decimales (format()); el libro y el superadmin
 * conservan los 4. Para que las cifras mostradas sigan sumando:
 *   - saldos y cambio → hacia ABAJO (nunca se muestra más de lo que hay);
 *   - costos → hacia ARRIBA (nunca se muestra menos de lo que se paga);
 *   - montos de un movimiento → al más cercano.
 */
class Money
{
    public const UNIT = 10000;

    /** Bs (decimal) → unidades enteras. */
    public static function units(float|int|string $bob): int
    {
        return (int) round(((float) $bob) * self::UNIT);
    }

    public static function bob(int $units): float
    {
        return $units / self::UNIT;
    }

    /**
     * Texto en Bs con coma decimal. $mode: 'floor' (saldos, por defecto),
     * 'ceil' (costos) o 'round' (montos de movimientos).
     */
    public static function format(int $units, int $decimals = 2, string $mode = 'floor'): string
    {
        $step = (int) (self::UNIT / (10 ** $decimals));

        $snapped = match (true) {
            $step <= 1       => $units,
            $mode === 'ceil'  => intdiv($units + $step - 1, $step) * $step,
            $mode === 'round' => intdiv($units + intdiv($step, 2), $step) * $step,
            default           => intdiv($units, $step) * $step,
        };

        return number_format($snapped / self::UNIT, $decimals, ',', '.');
    }

    /** ¿Es un monto positivo que en 2 decimales se vería como 0,00? */
    public static function belowCent(int $units): bool
    {
        return $units > 0 && $units < self::UNIT / 100;
    }

    /** "Bs 8,93" o, si es menos de un centavo, "menos de Bs 0,01" (para saldos/cambio). */
    public static function label(int $units): string
    {
        return static::belowCent($units) ? 'menos de Bs 0,01' : 'Bs ' . static::format($units);
    }

    /**
     * Precio de UNA moneda para mostrar con 2 decimales sin engañar: si vale Bs 1
     * o más, "Bs 11,07"; si vale menos (bronce = 0,0553), se muestra por 100
     * unidades: "Bs 5,53 por 100" (0,06 por unidad sería falso).
     */
    public static function price(int $unitPriceUnits): string
    {
        return $unitPriceUnits >= self::UNIT
            ? 'Bs ' . static::format($unitPriceUnits, 2, 'round') . ' c/u'
            : 'Bs ' . static::format($unitPriceUnits * 100, 2, 'round') . ' por 100';
    }
}
