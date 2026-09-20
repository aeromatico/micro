<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Ubicación (coordenadas) opcional de una dirección de envío, p. ej. compartida por el cliente en WhatsApp. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_addresses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_label', 120)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_addresses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_label']);
        });
    }
};
