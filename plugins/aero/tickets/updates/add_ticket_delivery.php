<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_tickets_tickets', 'public_token')) {
            Schema::table('aero_tickets_tickets', function (Blueprint $t) {
                $t->string('public_token', 40)->nullable()->unique();   // enlace privado de la entrada (≠ código de puerta)
                $t->unsignedSmallInteger('seq')->nullable();            // n.º dentro de la orden
            });
        }

        // Números de serie y enlaces para las entradas ya emitidas.
        DB::table('aero_tickets_tickets')->whereNull('public_token')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $r) {
                DB::table('aero_tickets_tickets')->where('id', $r->id)->update(['public_token' => \Illuminate\Support\Str::random(40)]);
            }
        });
        $last = [];
        DB::table('aero_tickets_tickets')->whereNull('seq')->whereNotNull('order_id')->orderBy('id')->chunkById(200, function ($rows) use (&$last) {
            foreach ($rows as $r) {
                $last[$r->order_id] = ($last[$r->order_id] ?? (int) DB::table('aero_tickets_tickets')->where('order_id', $r->order_id)->max('seq')) + 1;
                DB::table('aero_tickets_tickets')->where('id', $r->id)->update(['seq' => $last[$r->order_id]]);
            }
        });

        if (!$this->hasIndex('aero_tickets_tickets', 'aero_tickets_tickets_order_seq_unique')) {
            Schema::table('aero_tickets_tickets', fn (Blueprint $t) => $t->unique(['order_id', 'seq'], 'aero_tickets_tickets_order_seq_unique'));
        }

        if (!Schema::hasColumn('aero_tickets_orders', 'tickets_sent_at')) {
            Schema::table('aero_tickets_orders', function (Blueprint $t) {
                $t->timestamp('tickets_sent_at')->nullable();
                $t->unsignedSmallInteger('tickets_send_count')->default(0);
            });
        }
    }

    protected function hasIndex(string $table, string $name): bool
    {
        try {
            return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $name);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::table('aero_tickets_orders', fn (Blueprint $t) => $t->dropColumn(['tickets_sent_at', 'tickets_send_count']));
        Schema::table('aero_tickets_tickets', function (Blueprint $t) {
            $t->dropUnique('aero_tickets_tickets_order_seq_unique');
            $t->dropColumn(['public_token', 'seq']);
        });
    }
};
