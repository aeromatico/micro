<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // El evento privado por invitación ya no existe: sus eventos pasan a «privado por enlace» (con su token).
        DB::table('aero_tickets_events')->where('visibility', 'invite')->get(['id', 'access_token'])->each(function ($e) {
            DB::table('aero_tickets_events')->where('id', $e->id)->update(['visibility' => 'link', 'access_token' => $e->access_token ?: \Illuminate\Support\Str::random(32)]);
        });

        // «Sin reservas»: el tiempo de reserva por evento ya no se usa. (Las tablas/columnas de invitados quedan sin uso a propósito, sin borrar datos.)
        if (Schema::hasColumn('aero_tickets_events', 'reservation_minutes')) {
            Schema::table('aero_tickets_events', fn (Blueprint $t) => $t->dropColumn('reservation_minutes'));
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('aero_tickets_events', 'reservation_minutes')) {
            Schema::table('aero_tickets_events', fn (Blueprint $t) => $t->unsignedSmallInteger('reservation_minutes')->default(30));
        }
    }
};
