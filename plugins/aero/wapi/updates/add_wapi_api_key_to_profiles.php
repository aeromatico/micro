<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Override de credencial por perfil, mismo criterio que
 * Aero.Hello::add_credentials_to_profiles.php para zernio_api_key: `text`
 * porque Profile la cifra con Encryptable (ver Plugin::extendProfileModel()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_hello_profiles', function (Blueprint $table) {
            $table->text('wapi_api_key')->nullable()->after('zernio_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('aero_hello_profiles', function (Blueprint $table) {
            $table->dropColumn('wapi_api_key');
        });
    }
};
