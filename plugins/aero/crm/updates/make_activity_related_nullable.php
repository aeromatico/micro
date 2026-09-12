<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_activities', function (Blueprint $table) {
            $table->string('related_type')->nullable()->change();
            $table->unsignedBigInteger('related_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_activities', function (Blueprint $table) {
            $table->string('related_type')->nullable(false)->change();
            $table->unsignedBigInteger('related_id')->nullable(false)->change();
        });
    }
};
