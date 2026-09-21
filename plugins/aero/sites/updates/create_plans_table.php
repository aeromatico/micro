<?php

use Aero\Sites\Models\Plan;
use Aero\Sites\Models\Settings;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Planes de plataforma gestionables desde el backend. Reemplaza el catálogo
 * fijo de SignupPlans (negocio/pro) y los precios sueltos de Settings: se
 * siembran los dos planes existentes con los precios que ya estaban
 * configurados, y todos los plugins accesibles al tenant activados (sin
 * cambio de comportamiento hasta que el superadmin los ajuste).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_sites_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_pro')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('credits')->nullable();   // [{credit_type, amount}]
            $table->text('plugins')->nullable();   // ["Aero.Hello", ...] — null = sin restricción
            $table->text('features')->nullable();  // [{text}]
            $table->timestamps();
        });

        try {
            $plugins = array_values(array_unique(array_column(\Aero\Sites\Classes\ProFeatures::catalog(), 'plugin')));
        }
        catch (\Throwable $e) {
            $plugins = null;
        }

        $seed = [
            ['negocio', 'Negocio', 49, false, 1, [
                'Sitio web con generador de IA', 'Tienda online e inventario', 'CRM y cobranzas por WhatsApp', 'Pagos QR propios',
            ]],
            ['pro', 'Pro', 99, true, 2, [
                'Todo lo de Negocio', 'WhatsApp Business sin límites', 'Chatbots y automatización con IA', 'Soporte prioritario',
            ]],
        ];

        foreach ($seed as [$code, $name, $defaultPrice, $isPro, $order, $features]) {
            // Insert directo y no Plan::create(): el modelo ya conoce columnas de
            // migraciones posteriores (price_annual, trial_days) y al reaplicar el
            // plugin desde cero fallaría porque todavía no existen.
            DB::table('aero_sites_plans')->insert([
                'code'        => $code,
                'name'        => $name,
                'price'       => (float) Settings::get("signup_price_{$code}", $defaultPrice),
                'is_pro'      => $isPro,
                'is_featured' => $isPro,
                'sort_order'  => $order,
                'plugins'     => $plugins === null ? null : json_encode($plugins),
                'features'    => json_encode(array_map(fn ($t) => ['text' => $t], $features)),
                'credits'     => json_encode([]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('aero_sites_plans');
    }
};
