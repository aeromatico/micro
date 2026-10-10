<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Avisos de reservas (vía Aero.Notify/Hello): interruptor por negocio, horas de recordatorio y marca de envío. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_office_settings', 'notify_customers')) {
            Schema::table('aero_office_settings', function (Blueprint $t) {
                $t->boolean('notify_customers')->default(true)->after('auto_assign_worker');
                $t->unsignedInteger('reminder_hours')->default(24)->after('notify_customers')->comment('0 = sin recordatorio');
            });
        }

        if (!Schema::hasColumn('aero_office_bookings', 'reminder_sent_at')) {
            Schema::table('aero_office_bookings', function (Blueprint $t) {
                $t->timestamp('reminder_sent_at')->nullable();
                $t->index(['status', 'starts_at', 'reminder_sent_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_office_bookings', 'reminder_sent_at')) {
            Schema::table('aero_office_bookings', function (Blueprint $t) {
                $t->dropIndex(['status', 'starts_at', 'reminder_sent_at']);
                $t->dropColumn('reminder_sent_at');
            });
        }
        if (Schema::hasColumn('aero_office_settings', 'notify_customers')) {
            Schema::table('aero_office_settings', function (Blueprint $t) {
                $t->dropColumn(['notify_customers', 'reminder_hours']);
            });
        }
    }
};
