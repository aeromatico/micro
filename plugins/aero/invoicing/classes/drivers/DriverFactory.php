<?php namespace Aero\Invoicing\Classes\Drivers;

use Aero\Invoicing\Models\Issuer;

class DriverFactory
{
    /** Permite a los tests (o a otro plugin) sustituir un driver por código. */
    protected static array $overrides = [];

    public static function override(string $code, ?InvoicingDriver $driver): void
    {
        $driver ? self::$overrides[$code] = $driver : self::$overrides = array_diff_key(self::$overrides, [$code => 1]);
    }

    public static function for(Issuer $issuer): InvoicingDriver
    {
        $code = $issuer->driver ?: 'floogos';

        return self::$overrides[$code] ?? match ($code) {
            'simulated' => new SimulatedDriver(),
            'floogos'   => new FloogosDriver(),
            default     => throw new DriverException("Driver de facturación desconocido: {$code}"),
        };
    }
}
