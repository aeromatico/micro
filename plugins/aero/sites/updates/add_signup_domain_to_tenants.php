<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Dominio propio elegido opcionalmente en el alta (solo plan Pro, +Bs
 * Settings::getDomainRegistrationPrice()) — la disponibilidad se busca en
 * vivo contra la API de clouds.com.bo (otro sitio propio), pero el registro
 * real del dominio es un paso manual posterior del equipo: esta columna es
 * solo el registro de qué dominio pidió el cliente y ya pagó, para hacer
 * seguimiento — no hay automatización de compra del dominio todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->string('signup_domain')->nullable()->after('signup_payment_reference')
                ->comment('Dominio propio elegido en el alta (plan Pro, opcional) — registro manual pendiente del equipo, no automatizado.');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn('signup_domain');
        });
    }
};
