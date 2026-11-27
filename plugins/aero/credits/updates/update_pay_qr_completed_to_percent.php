<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * La comisión por QR cobrado deja de ser fija: es un porcentaje que calcula
 * Aero.Pay (QrCompletedCharge). La acción queda como interruptor y moneda;
 * su costo (1) es solo el mínimo por cobro.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('aero_credits_actions', 'public_description')) {
            return;
        }

        DB::table('aero_credits_actions')->where('code', 'pay.qr_completed')->update([
            'label'              => 'Comisión por cobro QR: 1 % del monto (el costo es el mínimo)',
            'default_cost'       => 1,
            'public_label'       => 'Comisión por cobro con QR',
            'unit'               => 'del monto cobrado',
            'public_description' => 'Sin costo los primeros 90 días desde la creación de tu cuenta. Después, 1 % de cada cobro, convertido a monedas de bronce y redondeado hacia arriba.',
            'updated_at'         => now(),
        ]);
    }

    public function down()
    {
        // Sin rollback: ver CLAUDE.md (nunca plugin:refresh/rollback).
    }
};
