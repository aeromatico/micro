<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_gym_measurements')) {
            return;
        }

        Schema::create('aero_gym_measurements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->unsignedBigInteger('member_id');
            $t->date('measured_on');
            $t->decimal('weight_kg', 5, 2);
            $t->decimal('height_cm', 5, 1)->nullable();
            $t->decimal('body_fat_pct', 4, 1)->nullable();
            $t->decimal('muscle_pct', 4, 1)->nullable();
            $t->decimal('waist_cm', 5, 1)->nullable();
            $t->decimal('chest_cm', 5, 1)->nullable();
            $t->decimal('hip_cm', 5, 1)->nullable();
            $t->decimal('arm_cm', 5, 1)->nullable();
            $t->decimal('thigh_cm', 5, 1)->nullable();
            $t->string('notes', 500)->nullable();
            $t->string('source', 8)->default('member')->comment('member, staff');
            $t->timestamps();
            $t->unique(['member_id', 'measured_on']);
            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_gym_measurements');
    }
};
