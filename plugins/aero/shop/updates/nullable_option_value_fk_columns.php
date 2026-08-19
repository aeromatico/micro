<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * El Repeater de October en modo relación (usado para gestionar "Valores"
 * dentro del form de una Opción) inserta una fila vacía en cuanto el usuario
 * hace clic en "Agregar" — antes de asignar la FK al padre y antes de que el
 * usuario escriba nada — y solo después la actualiza con tenant_id/
 * product_option_id reales (ver HasOneOrMany::add() en el core). Con estas
 * columnas NOT NULL sin default, ese primer insert falla siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_product_option_values', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->change();
            $table->foreignId('product_option_id')->nullable()->change();
            $table->string('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_product_option_values', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
            $table->foreignId('product_option_id')->nullable(false)->change();
            $table->string('value')->nullable(false)->change();
        });
    }
};
