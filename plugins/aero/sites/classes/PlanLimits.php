<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Tenant;
use Event;

/**
 * Límites numéricos por plan (p. ej. «cuentas de redes sociales conectadas»).
 * Cada plugin declara los suyos con el evento `aero.sites.registerPlanLimits`:
 *
 *   Event::listen('aero.sites.registerPlanLimits', fn () => [
 *       'social_accounts' => ['label' => '…', 'comment' => '…', 'plugin' => 'Aero.Social'],
 *   ]);
 *
 * El superadmin los fija en Sites → Planes → Límites. Vacío = sin límite.
 * Sin plan (tenant master o sin plan asignado) = sin límite.
 */
class PlanLimits
{
    protected static ?array $definitions = null;

    /** @return array<string, array{label:string, comment?:string, plugin?:string}> */
    public static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $out = [];
        foreach (array_filter(Event::dispatch('aero.sites.registerPlanLimits') ?: []) as $set) {
            foreach ((array) $set as $key => $def) {
                if (preg_match('/^[a-z0-9_]+$/', (string) $key) && !empty($def['label'])) {
                    $out[$key] = $def;
                }
            }
        }
        ksort($out);

        return self::$definitions = $out;
    }

    /** Límite del tenant para la clave, o null si no tiene. */
    public static function for(?int $tenantId, string $key): ?int
    {
        if (!$tenantId) {
            return null;
        }

        $plan = Tenant::with('plan')->find($tenantId)?->plan;

        return $plan?->limitFor($key);
    }

    public static function flush(): void
    {
        self::$definitions = null;
    }
}
