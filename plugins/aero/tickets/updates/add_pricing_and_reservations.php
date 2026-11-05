<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_tickets_events', 'reservation_minutes')) {
            Schema::table('aero_tickets_events', function (Blueprint $t) {
                $t->unsignedSmallInteger('reservation_minutes')->default(30);
            });
        }

        if (!Schema::hasColumn('aero_tickets_ticket_types', 'fee_payer')) {
            Schema::table('aero_tickets_ticket_types', function (Blueprint $t) {
                $t->decimal('fee_percent', 5, 2)->nullable();          // null = comisión por defecto de la plataforma
                $t->decimal('fee_fixed', 10, 2)->nullable();
                $t->string('fee_payer', 10)->nullable();                 // buyer | organizer | null = según la plataforma
            });
        }

        if (!Schema::hasColumn('aero_tickets_tickets', 'order_code')) {
            Schema::table('aero_tickets_tickets', function (Blueprint $t) {
                $t->decimal('base_amount', 10, 2)->default(0);
                $t->decimal('fee_amount', 10, 2)->default(0);
                $t->string('order_code', 16)->nullable()->index();
                $t->timestamp('expires_at')->nullable();
            });
            DB::table('aero_tickets_tickets')->where('amount', '>', 0)->update(['base_amount' => DB::raw('amount')]);
        }
    }

    public function down(): void
    {
        // Solo lo propio de esta versión.
        Schema::table('aero_tickets_tickets', fn (Blueprint $t) => $t->dropColumn(['base_amount', 'fee_amount', 'order_code', 'expires_at']));
        Schema::table('aero_tickets_ticket_types', fn (Blueprint $t) => $t->dropColumn(['fee_percent', 'fee_fixed', 'fee_payer']));
        Schema::table('aero_tickets_events', fn (Blueprint $t) => $t->dropColumn('reservation_minutes'));
    }
};
