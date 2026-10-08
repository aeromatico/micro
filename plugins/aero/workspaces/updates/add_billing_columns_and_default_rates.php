<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Cobro completo: cada turno real de chat y cada contratación guardan su
 * movimiento de créditos (para reembolsar y reportar), y los agentes reciben
 * tarifas por defecto según su rareza. Idempotente; nunca pisa una tarifa que
 * el superadmin ya haya puesto (> 0).
 */
return new class extends Migration
{
    /** Puntos por defecto por rareza: [contratación, mensaje de chat]. */
    private const DEFAULTS = ['r' => [0, 1], 'sr' => [50, 3], 'ssr' => [150, 5]];

    public function up(): void
    {
        if (!Schema::hasColumn('aero_workspaces_messages', 'charged_points')) {
            Schema::table('aero_workspaces_messages', function (Blueprint $table) {
                $table->unsignedInteger('charged_points')->default(0);
                $table->unsignedBigInteger('credit_transaction_id')->nullable();
            });
        }

        if (!Schema::hasColumn('aero_workspaces_hires', 'credit_transaction_id')) {
            Schema::table('aero_workspaces_hires', function (Blueprint $table) {
                $table->unsignedBigInteger('credit_transaction_id')->nullable();
            });
        }

        if (!Schema::hasColumn('aero_workspaces_tasks', 'refunded_at')) {
            Schema::table('aero_workspaces_tasks', function (Blueprint $table) {
                $table->timestamp('refunded_at')->nullable();
            });
        }

        // Un solo tipo de encargo: el `flujo` de Link pasa a ser su `encargo`.
        foreach (DB::table('aero_workspaces_task_rates')->where('task_type', 'flujo')->get() as $row) {
            $has = DB::table('aero_workspaces_task_rates')->where('staff_id', $row->staff_id)->where('task_type', 'encargo')->exists();

            if ($has) {
                DB::table('aero_workspaces_task_rates')->where('id', $row->id)->delete();
            }
            else {
                DB::table('aero_workspaces_task_rates')->where('id', $row->id)->update(['task_type' => 'encargo']);
            }
        }

        foreach (DB::table('aero_workspaces_staff')->get(['id', 'rarity', 'is_orchestrator']) as $staff) {
            [$hire, $chat] = self::DEFAULTS[$staff->rarity] ?? self::DEFAULTS['r'];
            $now = now();

            // El orquestador no se contrata.
            $hire = $staff->is_orchestrator ? 0 : $hire;

            $rate = DB::table('aero_workspaces_staff_rates')->where('staff_id', $staff->id)->first();

            if (!$rate) {
                DB::table('aero_workspaces_staff_rates')->insert(['staff_id' => $staff->id, 'hire_fee' => $hire, 'created_at' => $now, 'updated_at' => $now]);
            }
            elseif ((float) $rate->hire_fee == 0.0 && $hire > 0) {
                DB::table('aero_workspaces_staff_rates')->where('id', $rate->id)->update(['hire_fee' => $hire, 'updated_at' => $now]);
            }

            $turn = DB::table('aero_workspaces_task_rates')->where('staff_id', $staff->id)->where('task_type', 'chat_turn')->first();

            if (!$turn) {
                DB::table('aero_workspaces_task_rates')->insert(['staff_id' => $staff->id, 'task_type' => 'chat_turn', 'fee' => $chat, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        // Sin reversa destructiva: las columnas y tarifas son aditivas.
    }
};
