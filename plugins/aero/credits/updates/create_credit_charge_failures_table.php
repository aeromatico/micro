<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Cobros que no se pudieron hacer (saldo agotado en carrera, acción sin
 * catálogo…) y que el plugin llamador decidió no propagar. No es parte del
 * ledger: es solo auditoría para el superadmin.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('aero_credits_charge_failures')) {
            return;
        }

        Schema::create('aero_credits_charge_failures', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('action_code')->index();
            $table->string('source_plugin', 60)->nullable()->index();
            $table->string('reason', 500);
            $table->text('context')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down()
    {
        // Sin rollback: ver CLAUDE.md (nunca plugin:refresh/rollback).
    }
};
