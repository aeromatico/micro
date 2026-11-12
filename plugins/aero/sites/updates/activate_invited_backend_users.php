<?php namespace Aero\Sites\Updates;

use Db;
use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Las cuentas creadas por invitación de equipo quedaron con is_activated=0 y
 * October no deja iniciar sesión así. Se activan las que nunca ingresaron
 * (su contraseña es aleatoria: el acceso lo da el enlace de activación).
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('aero_sites_tenant_invites')) {
            return;
        }

        $ids = Db::table('aero_sites_tenant_invites')->whereNotNull('backend_user_id')->pluck('backend_user_id')->all();

        if ($ids) {
            Db::table('backend_users')->whereIn('id', $ids)->where('is_activated', 0)->update(['is_activated' => 1, 'activated_at' => now()]);
        }
    }

    public function down()
    {
    }
};
