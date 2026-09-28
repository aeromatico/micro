<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_services_services', 'plans')) {
            Schema::table('aero_services_services', function (Blueprint $table) {
                $table->json('plans')->nullable()->after('code');
            });
        }

        foreach (Db::table('aero_services_services')->get() as $service) {
            $plan = [
                'name'           => 'Estándar',
                'type'           => $service->type ?? 'one_time',
                'billing_period' => $service->billing_period,
                'pricing_mode'   => $service->pricing_mode ?? 'money',
                'price'          => $service->price,
                'currency'       => $service->currency ?? 'BOB',
                'price_from'     => (bool) $service->price_from,
                'setup_fee'      => $service->setup_fee,
                'credit_price'   => $service->credit_price,
                'credit_type'    => $service->credit_type,
                'delivery_days'  => $service->delivery_days,
            ];

            Db::table('aero_services_services')->where('id', $service->id)->update([
                'plans' => json_encode([$plan]),
            ]);
        }

        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'price', 'price_from', 'setup_fee', 'currency', 'billing_period',
                'delivery_days', 'pricing_mode', 'credit_price', 'credit_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->string('type', 20)->default('one_time')->after('code');
            $table->decimal('price', 12, 2)->nullable()->after('type');
            $table->boolean('price_from')->default(false)->after('price');
            $table->decimal('setup_fee', 12, 2)->nullable()->after('price_from');
            $table->string('currency', 3)->default('BOB')->after('setup_fee');
            $table->string('billing_period', 20)->nullable()->after('currency');
            $table->unsignedSmallInteger('delivery_days')->nullable()->after('billing_period');
            $table->string('pricing_mode', 10)->default('money')->after('type');
            $table->unsignedInteger('credit_price')->nullable()->after('setup_fee');
            $table->string('credit_type', 50)->nullable()->after('credit_price');
        });

        foreach (Db::table('aero_services_services')->get() as $service) {
            $plan = collect(json_decode((string) $service->plans, true) ?: [])->first() ?: [];

            Db::table('aero_services_services')->where('id', $service->id)->update([
                'type'           => $plan['type'] ?? 'one_time',
                'billing_period' => $plan['billing_period'] ?? null,
                'pricing_mode'   => $plan['pricing_mode'] ?? 'money',
                'price'          => $plan['price'] ?? null,
                'currency'       => $plan['currency'] ?? 'BOB',
                'price_from'     => (bool) ($plan['price_from'] ?? false),
                'setup_fee'      => $plan['setup_fee'] ?? null,
                'credit_price'   => $plan['credit_price'] ?? null,
                'credit_type'    => $plan['credit_type'] ?? null,
                'delivery_days'  => $plan['delivery_days'] ?? null,
            ]);
        }

        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->dropColumn('plans');
        });
    }
};
