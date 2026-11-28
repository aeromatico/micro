<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_church_service_times')) {
            Schema::create('aero_church_service_times', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->string('title', 120);
                $t->unsignedTinyInteger('day')->comment('1 = lunes … 7 = domingo');
                $t->string('starts_at', 5)->comment('HH:MM, hora local de la iglesia');
                $t->string('ends_at', 5)->nullable();
                $t->string('location', 150)->nullable();
                $t->string('note', 200)->nullable();
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('week_position')->default(0)->comment('day * 10000 + HHMM, para ordenar la semana');
                $t->timestamps();
                $t->index(['tenant_id', 'week_position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_church_service_times');
    }
};
