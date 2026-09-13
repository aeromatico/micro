<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->string('plan')->nullable()->after('niche_type')->comment('Plan contratado en el alta: negocio, pro');
            $table->decimal('plan_price', 8, 2)->nullable()->after('plan')->comment('Precio pagado al activarse, en Bs — snapshot histórico aunque el precio del plan cambie después');
            $table->unsignedBigInteger('signup_qr_code_id')->nullable()->after('plan_price')->comment('QrCode (aero_qrbo_qr_codes) emitido para cobrar el alta');
            $table->string('signup_payment_reference')->nullable()->unique()->after('signup_qr_code_id')->comment('internal_reference del QrCode de alta — usado para matchear el webhook de pago');
        });

        // `status` es un enum nativo — se agrega 'pending_payment' (tenant
        // reservado por handle mientras se espera el pago del alta, aún sin
        // provisionar sitio ni admin).
        DB::statement("ALTER TABLE aero_sites_tenants MODIFY COLUMN status ENUM('active', 'inactive', 'suspended', 'pending_payment') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_price', 'signup_qr_code_id', 'signup_payment_reference']);
        });

        DB::statement("ALTER TABLE aero_sites_tenants MODIFY COLUMN status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active'");
    }
};
