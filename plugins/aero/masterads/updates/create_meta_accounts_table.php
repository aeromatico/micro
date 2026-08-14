<?php namespace Aero\MasterAds\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Crea la tabla `aero_masterads_meta_accounts`.
 *
 * Almacena cuentas publicitarias de Meta conectadas vía OAuth.
 * Los tokens (access_token, refresh_token) se almacenan cifrados a nivel
 * de modelo mediante mutators (Crypt::encrypt) — el tipo `text` reserva el
 * espacio suficiente para el payload cifrado.
 *
 * Índices:
 *  - UNIQUE(workspace_id, meta_act_id) — evita duplicar la misma cuenta Meta
 *    dentro de un Workspace y soporta el upsert idempotente del flujo OAuth.
 *  - INDEX(expires_at) — acelera la búsqueda nocturna de tokens próximos a
 *    expirar usada por `masterads:rotate-tokens`.
 *
 * Validates Requirements 2.4, 17.4, 17.5.
 */
class CreateMetaAccountsTable extends Migration
{
    public function up()
    {
        Schema::create('aero_masterads_meta_accounts', function ($table) {
            $table->increments('id');
            $table->integer('workspace_id')->unsigned();
            $table->string('meta_act_id', 64);
            $table->string('name', 255)->nullable();
            $table->char('currency', 3);
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'meta_act_id'], 'aero_masterads_meta_accounts_ws_act_unique');
            $table->index('expires_at', 'aero_masterads_meta_accounts_expires_at_index');

            $table->foreign('workspace_id')
                ->references('id')
                ->on('aero_masterads_workspaces')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_masterads_meta_accounts');
    }
}
