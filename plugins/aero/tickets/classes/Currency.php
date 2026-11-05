<?php namespace Aero\Tickets\Classes;

/** Monedas. Hoy solo BOB está habilitada; USD ya es válida en los datos pero no seleccionable. */
class Currency
{
    public const ENABLED = ['BOB'];
    public const KNOWN = ['BOB' => 'Bs', 'USD' => 'USD'];

    public static function options(): array
    {
        return array_intersect_key(['BOB' => 'Bolivianos (Bs)', 'USD' => 'Dólares (USD)'], array_flip(self::ENABLED));
    }

    public static function format(float|string|null $amount, string $currency = 'BOB'): string
    {
        $n = number_format((float) $amount, 2, ',', '.');

        return ($currency === 'BOB' ? 'Bs ' : $currency . ' ') . $n;
    }
}
