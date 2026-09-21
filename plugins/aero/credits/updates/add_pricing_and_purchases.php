<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Precio de venta por moneda (Bs por unidad), marca de intercambiable (oro no)
 * y compras por QR. price_bob se siembra desde usd_value × tipo de cambio de
 * Aero.Sites (editable después por el superadmin).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_credits_types', function (Blueprint $table) {
            $table->decimal('price_bob', 12, 4)->default(0)->after('usd_value');
            $table->boolean('is_exchangeable')->default(true)->after('price_bob');
        });

        $rate = class_exists(\Aero\Sites\Models\Settings::class) ? (float) \Aero\Sites\Models\Settings::getUsdToBobRate() : 6.96;
        DB::statement('UPDATE aero_credits_types SET price_bob = ROUND(usd_value * ?, 4)', [$rate ?: 6.96]);
        DB::table('aero_credits_types')->where('code', 'oro')->update(['is_exchangeable' => false]);

        Schema::create('aero_credits_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->decimal('amount_bob', 10, 2);
            $table->string('status', 16)->default('pending'); // pending | paid | expired | cancelled
            $table->text('lines');                             // [{credit_type_id, code, bob, price_bob, coins}]
            $table->unsignedBigInteger('qr_code_id')->nullable();
            $table->string('payment_reference', 100)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_purchases');
        Schema::table('aero_credits_types', function (Blueprint $table) {
            $table->dropColumn(['price_bob', 'is_exchangeable']);
        });
    }
};
