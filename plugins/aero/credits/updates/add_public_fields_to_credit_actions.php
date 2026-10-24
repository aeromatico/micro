<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Tarifario: cada acción declara cómo se la presenta al tenant (categoría,
 * nombre claro, unidad). Backfill por prefijo del código; sin regla = null
 * (CreditUsage resuelve el fallback al leer).
 */
return new class extends Migration
{
    protected const CATEGORIES = [
        'hello' => 'Mensajería', 'chat' => 'Mensajería', 'sms' => 'Mensajería',
        'aifields' => 'Inteligencia artificial', 'chatbots' => 'Inteligencia artificial',
        'hub' => 'Datos externos', 'api' => 'API', 'workflows' => 'Automatización',
        'connector' => 'Automatización', 'pay' => 'Pagos', 'services' => 'Servicios',
        'workspaces' => 'Servicios', 'producer' => 'Video',
    ];

    public function up()
    {
        if (!Schema::hasColumn('aero_credits_actions', 'category')) {
            Schema::table('aero_credits_actions', function ($table) {
                $table->string('category', 40)->nullable()->after('plugin');
                $table->string('public_label')->nullable()->after('label');
                $table->string('public_description', 500)->nullable()->after('public_label');
                $table->string('unit', 40)->nullable()->after('default_cost');
                $table->boolean('is_public')->default(false)->after('is_active');
            });
        }

        foreach (DB::table('aero_credits_actions')->whereNull('category')->get(['id', 'code']) as $row) {
            $category = self::CATEGORIES[strstr($row->code, '.', true) ?: $row->code] ?? null;

            if ($category) {
                DB::table('aero_credits_actions')->where('id', $row->id)->update(['category' => $category]);
            }
        }
    }

    public function down()
    {
        // Sin rollback: ver CLAUDE.md (nunca plugin:refresh/rollback).
    }
};
