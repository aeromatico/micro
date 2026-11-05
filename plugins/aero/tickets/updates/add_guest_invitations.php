<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_tickets_guests', 'companions_allowed')) {
            Schema::table('aero_tickets_guests', function (Blueprint $t) {
                $t->unsignedTinyInteger('companions_allowed')->default(0);
                $t->string('invitation_kind', 10)->default('single');   // single | couple | family | group
                $t->string('status', 10)->default('active');            // active | revoked
                $t->timestamp('revoked_at')->nullable();
                $t->string('revoke_reason')->nullable();
                $t->index(['event_id', 'status']);
            });
            DB::table('aero_tickets_guests')->update([
                'companions_allowed' => DB::raw('CASE WHEN party_size > 1 THEN party_size - 1 ELSE 0 END'),
                'invitation_kind' => DB::raw("CASE WHEN party_size <= 1 THEN 'single' WHEN party_size = 2 THEN 'couple' ELSE 'group' END"),
            ]);
        }

        if (!Schema::hasColumn('aero_tickets_tickets', 'refund_pending')) {
            Schema::table('aero_tickets_tickets', function (Blueprint $t) {
                $t->boolean('refund_pending')->default(false);          // anulada después de haber sido pagada
            });
        }
    }

    public function down(): void
    {
        Schema::table('aero_tickets_tickets', fn (Blueprint $t) => $t->dropColumn('refund_pending'));
        Schema::table('aero_tickets_guests', function (Blueprint $t) {
            $t->dropIndex(['event_id', 'status']);
            $t->dropColumn(['companions_allowed', 'invitation_kind', 'status', 'revoked_at', 'revoke_reason']);
        });
    }
};
