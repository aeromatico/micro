<?php namespace Aero\Invoicing\Classes;

use Aero\Invoicing\Classes\Drivers\DriverFactory;
use Aero\Invoicing\Models\Cufd;
use Aero\Invoicing\Models\Issuer;
use Illuminate\Support\Facades\Cache;

/**
 * La CUFD vale 24 h y es única por emisor (NIT + sucursal + punto de venta).
 * Se renueva solo cuando hace falta (no cada día por cada tenant): cada
 * renovación es una ejecución del proveedor.
 */
class CufdManager
{
    /** Margen: no emitir con una CUFD a punto de vencer. */
    public const MARGIN_MINUTES = 10;

    public static function current(Issuer $issuer): Cufd
    {
        if ($cufd = self::valid($issuer)) {
            return $cufd;
        }

        // Evita que dos emisiones simultáneas pidan dos CUFD.
        return Cache::lock("aero-invoicing:cufd:{$issuer->id}", 30)->block(10, function () use ($issuer) {
            if ($cufd = self::valid($issuer)) {
                return $cufd;
            }

            $data = DriverFactory::for($issuer)->obtainCufd($issuer);

            return Cufd::create([
                'issuer_id'    => $issuer->id,
                'tenant_id'    => $issuer->tenant_id,
                'cuis'         => $data['cuis'],
                'cufd'         => $data['cufd'],
                'control_code' => $data['control_code'],
                'address'      => $data['address'],
                'valid_until'  => $data['valid_until'],
            ]);
        });
    }

    protected static function valid(Issuer $issuer): ?Cufd
    {
        return Cufd::where('issuer_id', $issuer->id)
            ->where('valid_until', '>', now()->addMinutes(self::MARGIN_MINUTES))
            ->orderByDesc('valid_until')
            ->first();
    }
}
