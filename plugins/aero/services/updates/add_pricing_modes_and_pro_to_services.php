<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->string('pricing_mode', 10)->default('money')->after('type');   // money | credits | both
            $table->unsignedInteger('credit_price')->nullable()->after('setup_fee');
            $table->string('credit_type', 50)->nullable()->after('credit_price');  // código de Aero.Credits (azul, rojo…)
            $table->boolean('has_pro')->default(false)->after('is_featured');
            $table->json('pro_features')->nullable()->after('has_pro');
        });
    }

    public function down(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'credit_price', 'credit_type', 'has_pro', 'pro_features']);
        });
    }
};
