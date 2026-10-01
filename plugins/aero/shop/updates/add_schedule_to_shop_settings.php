<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->string('schedule_mode', 20)->default('always_open'); // always_open | scheduled | online
            $table->json('schedule_hours')->nullable();
            $table->boolean('accepting_orders')->default(true);
            $table->string('timezone', 50)->default('America/La_Paz');
        });

        // Los horarios que el modo Restaurante guardaba en restaurant_config pasan al horario general.
        foreach (Db::table('aero_shop_settings')->whereNotNull('restaurant_config')->get() as $row) {
            $cfg = json_decode($row->restaurant_config, true) ?: [];
            if (!empty($cfg['hours'])) {
                Db::table('aero_shop_settings')->where('id', $row->id)->update([
                    'schedule_mode' => 'scheduled', 'schedule_hours' => json_encode($cfg['hours']),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->dropColumn(['schedule_mode', 'schedule_hours', 'accepting_orders', 'timezone']);
        });
    }
};
