<?php namespace Aero\Sites\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Borra los datos de un tenant en TODAS las tablas con columna tenant_id (los
 * plugins no tienen que registrar su propia limpieza). Se ejecuta al purgar un
 * tenant (evento aero.sites.tenant.purging), antes de que Tenant::purge() quite
 * el tenant en sí.
 *
 * Sólo filtra por tenant_id exacto: nunca borra filas de otros tenants.
 */
class TenantDataPurger
{
    /** Tablas que NO se tocan aquí. */
    protected static array $skip = [
        // Los borra Tenant::purge() él mismo (y limpia sus archivos adjuntos).
        'aero_sites_tenants', 'aero_sites_tenant_users', 'aero_sites_pages', 'aero_sites_seo_configs',
        'aero_sites_contact_configs', 'aero_sites_contact_submissions', 'aero_sites_api_tokens',
        'aero_sites_domains', 'aero_notify_channels',
    ];

    /** Prefijos que se conservan: el libro de créditos es contable y está protegido por la base. */
    protected static array $skipPrefixes = ['aero_credits_'];

    public static function tables(): array
    {
        $out = [];
        foreach (Schema::getTables() as $t) {
            $name = $t['name'];
            if (!str_starts_with($name, 'aero_') || in_array($name, static::$skip, true)) {
                continue;
            }
            foreach (static::$skipPrefixes as $pre) {
                if (str_starts_with($name, $pre)) {
                    continue 2;
                }
            }
            if (Schema::hasColumn($name, 'tenant_id')) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * @return array<string,int> filas borradas (o que se borrarían con $dry) por tabla
     */
    public static function purge(int $tenantId, bool $dry = false): array
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('tenant_id inválido');
        }

        $result = [];
        $driver = DB::getDriverName();
        if (!$dry && $driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach (static::tables() as $table) {
                try {
                    $q = DB::table($table)->where('tenant_id', $tenantId);
                    $n = $dry ? $q->count() : $q->delete();
                    if ($n) {
                        $result[$table] = $n;
                    }
                } catch (\Throwable $e) {
                    Log::warning("aero.sites: no se pudo limpiar {$table} del tenant {$tenantId}: " . $e->getMessage());
                }
            }
        } finally {
            if (!$dry && $driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        return $result;
    }
}
