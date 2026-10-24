<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Cobro en créditos de cada pedido. El costo vive en el estilo (monto y color);
 * el catálogo de Créditos solo recibe la acción para el tarifario y la auditoría.
 * Los montos de partida se ajustan en Productor → Estilos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_producer_orders') && !Schema::hasColumn('aero_producer_orders', 'credit_transaction_id')) {
            Schema::table('aero_producer_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('credit_transaction_id')->nullable();
                $table->timestamp('refunded_at')->nullable();
            });
        }

        if (Schema::hasTable('aero_producer_styles') && !Schema::hasColumn('aero_producer_styles', 'credit_type')) {
            Schema::table('aero_producer_styles', function (Blueprint $table) {
                $table->string('credit_type', 20)->default('plata');
            });

            // Solo donde nadie fijó un precio todavía: presentador 20 de plata (~US$2), explicativo 50 (~US$5).
            \DB::table('aero_producer_styles')->where('kind', 'presenter')->where('credit_cost', 0)->update(['credit_cost' => 20]);
            \DB::table('aero_producer_styles')->where('kind', 'explainer')->where('credit_cost', 0)->update(['credit_cost' => 50]);
        }

        // La clase puede existir sin que Credits haya migrado todavía (instalación nueva, tests).
        if (!class_exists(\Aero\Credits\Models\CreditAction::class)
            || !Schema::hasTable('aero_credits_types')
            || !Schema::hasTable('aero_credits_actions')
            || !Schema::hasColumn('aero_credits_actions', 'category')) {
            return;
        }

        $type = \Aero\Credits\Models\CreditType::where('code', 'plata')->first()
            ?: \Aero\Credits\Models\CreditType::orderBy('sort_order')->first();

        if (!$type) {
            return;
        }

        \Aero\Credits\Models\CreditAction::firstOrCreate(
            ['code' => 'producer.video'],
            [
                'label'              => 'Video de Productor (presentador o explicativo)',
                'plugin'             => 'Aero.Producer',
                'credit_type_id'     => $type->id,
                'default_cost'       => 20,
                'category'           => 'Video',
                'public_label'       => 'Video con presentador o explicativo',
                'public_description' => 'El costo depende del estilo que elijas y se muestra antes de enviar el pedido.',
                'unit'               => 'por video',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_producer_orders', 'credit_transaction_id')) {
            Schema::table('aero_producer_orders', fn (Blueprint $t) => $t->dropColumn(['credit_transaction_id', 'refunded_at']));
        }
        if (Schema::hasColumn('aero_producer_styles', 'credit_type')) {
            Schema::table('aero_producer_styles', fn (Blueprint $t) => $t->dropColumn('credit_type'));
        }
    }
};
