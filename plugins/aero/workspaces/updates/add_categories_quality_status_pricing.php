<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Catálogo de agentes con datos propios en lugar de listas fijas:
 *  - Categorías dinámicas (tabla propia, administradas por el superadmin).
 *  - Calidad 1–5 estrellas en lugar de «rareza» (R/SR/SSR).
 *  - Estado del agente: En entrenamiento, Online, No disponible.
 *  - Tarifa por agente: gratis o en créditos de un tipo de Aero.Credits.
 *  - Planes a los que corresponde (vacío = todos).
 * Es aditiva: la columna `rarity` se conserva (sin uso en las pantallas).
 */
return new class extends Migration
{
    public const CATEGORIES = [
        'video' => 'Video', 'imagen' => 'Imagen', 'contenido' => 'Contenido', 'ppt' => 'Presentaciones', 'info' => 'Investigación y datos',
        'automatizacion' => 'Automatización', 'ecommerce' => 'eCommerce', 'web' => 'Desarrollo web', 'integraciones' => 'Integraciones y APIs',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('aero_workspaces_categories')) {
            Schema::create('aero_workspaces_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 40)->unique();
                $table->string('label', 80);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Las categorías en uso (también las que no estaban en la lista fija) pasan a la tabla.
        $used = DB::table('aero_workspaces_staff')->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')->all();
        $order = 0;

        foreach (static::CATEGORIES + array_combine($used, array_map(fn ($c) => ucfirst($c), $used)) as $code => $label) {
            if (!DB::table('aero_workspaces_categories')->where('code', $code)->exists()) {
                DB::table('aero_workspaces_categories')->insert(['code' => $code, 'label' => $label, 'sort_order' => $order, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $order += 10;
        }

        Schema::table('aero_workspaces_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('aero_workspaces_staff', 'quality')) {
                $table->unsignedTinyInteger('quality')->default(3)->after('rarity');
            }
            if (!Schema::hasColumn('aero_workspaces_staff', 'status')) {
                $table->string('status', 16)->default('online')->after('quality'); // training | online | unavailable
            }
            if (!Schema::hasColumn('aero_workspaces_staff', 'fee_mode')) {
                $table->string('fee_mode', 8)->default('free')->after('status');   // free | credits
            }
            if (!Schema::hasColumn('aero_workspaces_staff', 'credit_type')) {
                $table->string('credit_type', 40)->nullable()->after('fee_mode');
            }
            if (!Schema::hasColumn('aero_workspaces_staff', 'plan_codes')) {
                $table->text('plan_codes')->nullable()->after('credit_type');
            }
        });

        // Datos existentes: la rareza se traduce a estrellas, is_active a estado y las tarifas a «créditos».
        DB::table('aero_workspaces_staff')->where('rarity', 'ssr')->update(['quality' => 5]);
        DB::table('aero_workspaces_staff')->where('rarity', 'sr')->update(['quality' => 4]);
        DB::table('aero_workspaces_staff')->where('rarity', 'r')->update(['quality' => 3]);
        DB::table('aero_workspaces_staff')->where('is_active', true)->update(['status' => 'online']);
        DB::table('aero_workspaces_staff')->where('is_active', false)->update(['status' => 'unavailable']);

        $default = 'azul';

        if (Schema::hasTable('system_settings')) {
            $raw = DB::table('system_settings')->where('item', 'aero_workspaces_settings')->value('value');
            $default = trim((string) (json_decode((string) $raw, true)['credit_type'] ?? '')) ?: 'azul';
        }

        $paid = DB::table('aero_workspaces_staff_rates')->where('hire_fee', '>', 0)->pluck('staff_id')->all();

        if (Schema::hasTable('aero_workspaces_task_rates')) {
            $paid = array_unique(array_merge($paid, DB::table('aero_workspaces_task_rates')->where('fee', '>', 0)->pluck('staff_id')->all()));
        }

        if ($paid) {
            DB::table('aero_workspaces_staff')->whereIn('id', $paid)->update(['fee_mode' => 'credits', 'credit_type' => $default]);
        }
    }

    public function down(): void
    {
        // Sin reversa destructiva: es aditiva.
    }
};
