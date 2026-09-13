<?php

use October\Rain\Database\Updates\Seeder;

/**
 * Copia las filas de aero_sites_notification_channels (sistema legacy de
 * Aero.Sites, solo usado por el contacto) a aero_notify_channels. El blob
 * `config` queda tal cual: ambas tablas lo cifran con Crypt sobre el mismo
 * APP_KEY, así que es portable sin desencriptar/re-encriptar.
 *
 * A la fecha de esta migración solo había 2 filas en producción (tenant 2 =
 * email, tenant 11 = whatsapp) — bajo riesgo, pero igual se corre como
 * seeder (upsert por tenant_id+channel) para no duplicar si se re-ejecuta.
 */
return new class extends Seeder
{
    public function run(): void
    {
        if (!\Schema::hasTable('aero_sites_notification_channels')) {
            return;
        }

        $legacyRows = \DB::table('aero_sites_notification_channels')->get();

        foreach ($legacyRows as $row) {
            $exists = \DB::table('aero_notify_channels')
                ->where('tenant_id', $row->tenant_id)
                ->where('channel', $row->type)
                ->exists();

            if ($exists) {
                continue;
            }

            \DB::table('aero_notify_channels')->insert([
                'tenant_id'  => $row->tenant_id,
                'channel'    => $row->type,
                'label'      => $row->label,
                'config'     => $row->config,
                'is_enabled' => $row->is_enabled,
                'sort_order' => $row->sort_order,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }
};
