<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_ai_generations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending')->after('tenant_id');
            $table->string('step', 40)->nullable()->after('status');
            $table->unsignedBigInteger('result_page_id')->nullable()->after('step');

            $table->foreign('result_page_id')->references('id')->on('aero_sites_pages')->nullOnDelete();
        });

        // Registros históricos (previos a este campo) ya terminaron una forma u otra.
        \Db::table('aero_sites_ai_generations')->where('success', true)->update(['status' => 'done']);
        \Db::table('aero_sites_ai_generations')->where('success', false)->update(['status' => 'failed']);
    }

    public function down(): void
    {
        Schema::table('aero_sites_ai_generations', function (Blueprint $table) {
            $table->dropForeign(['result_page_id']);
            $table->dropColumn(['status', 'step', 'result_page_id']);
        });
    }
};
