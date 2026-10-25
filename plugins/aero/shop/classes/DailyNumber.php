<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\ShopSettings;
use Db;

/**
 * Número del día de un pedido de restaurante: 1, 2, 3… por tenant y se reinicia cada día (a medianoche, en la
 * zona horaria de la tienda). Es lo que el comensal ve y se dice en el salón; el número de pedido (PED-000018)
 * no se reinicia y sigue siendo el identificador único.
 */
class DailyNumber
{
    /** @return array{0: string, 1: int} [fecha Y-m-d, número] */
    public static function next(int $tenantId): array
    {
        $tz = ShopSettings::where('tenant_id', $tenantId)->value('timezone') ?: 'America/La_Paz';
        $day = now($tz)->toDateString();

        return Db::transaction(function () use ($tenantId, $day) {
            // Fila del día (idempotente) y se bloquea para que dos pedidos simultáneos no repitan número.
            Db::table('aero_shop_daily_counters')->insertOrIgnore(['tenant_id' => $tenantId, 'day' => $day, 'last' => 0]);
            $row = Db::table('aero_shop_daily_counters')->where('tenant_id', $tenantId)->where('day', $day)->lockForUpdate()->first();
            $n = (int) $row->last + 1;
            Db::table('aero_shop_daily_counters')->where('tenant_id', $tenantId)->where('day', $day)->update(['last' => $n]);
            // Limpieza: no hace falta guardar más de una semana de contadores.
            Db::table('aero_shop_daily_counters')->where('tenant_id', $tenantId)->where('day', '<', now()->subDays(7)->toDateString())->delete();

            return [$day, $n];
        });
    }

    /** Hoy en la zona horaria de la tienda (Y-m-d). */
    public static function today(int $tenantId): string
    {
        return now(ShopSettings::where('tenant_id', $tenantId)->value('timezone') ?: 'America/La_Paz')->toDateString();
    }
}
