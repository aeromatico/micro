<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_producer_orders') && !Schema::hasColumn('aero_producer_orders', 'provider_ref')) {
            Schema::table('aero_producer_orders', function (Blueprint $table) {
                $table->string('provider_ref', 64)->nullable();   // id del video en el proveedor mientras se produce
                $table->timestamp('produced_at')->nullable();
            });
        }

        if (Schema::hasTable('aero_producer_styles') && !Schema::hasColumn('aero_producer_styles', 'motion_prompt')) {
            Schema::table('aero_producer_styles', function (Blueprint $table) {
                $table->text('motion_prompt')->nullable();        // cómo se mueve el presentador
            });

            \DB::table('aero_producer_styles')->where('slug', 'presentador-calle')->update([
                'motion_prompt' => 'Walks slowly and naturally along a quiet city sidewalk toward the camera, relaxed pace, gesturing casually with one hand while talking, looking at the camera',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_producer_orders', 'provider_ref')) {
            Schema::table('aero_producer_orders', fn (Blueprint $t) => $t->dropColumn(['provider_ref', 'produced_at']));
        }
        if (Schema::hasColumn('aero_producer_styles', 'motion_prompt')) {
            Schema::table('aero_producer_styles', fn (Blueprint $t) => $t->dropColumn('motion_prompt'));
        }
    }
};
