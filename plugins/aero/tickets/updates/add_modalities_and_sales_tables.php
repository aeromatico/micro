<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_tickets_events', 'registration')) {
            Schema::table('aero_tickets_events', function (Blueprint $t) {
                $t->string('registration', 10)->default('open')->after('visibility'); // open | approval
            });
        }

        // private → enlace; mixed → público (las cortesías/invitaciones ahora son tipos de entrada).
        DB::table('aero_tickets_events')->where('visibility', 'private')->update(['visibility' => 'link']);
        DB::table('aero_tickets_events')->where('visibility', 'mixed')->update(['visibility' => 'public']);

        // Funciones (fechas/lugares adicionales). Sin UI aún: la estructura queda lista.
        if (!Schema::hasTable('aero_tickets_sessions')) {
            Schema::create('aero_tickets_sessions', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id');
                $t->string('name');
                $t->dateTime('starts_at')->nullable();
                $t->dateTime('ends_at')->nullable();
                $t->string('venue_name')->nullable();
                $t->string('address')->nullable();
                $t->string('city', 100)->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->unsignedInteger('capacity')->nullable();
                $t->string('status', 12)->default('draft');
                $t->integer('sort_order')->default(0);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index('event_id');
            });
        }

        if (!Schema::hasTable('aero_tickets_ticket_types')) {
            Schema::create('aero_tickets_ticket_types', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id');
                $t->unsignedInteger('session_id')->nullable();
                $t->string('name');
                $t->text('description')->nullable();
                $t->string('kind', 10)->default('free');          // free | paid | courtesy
                $t->decimal('price', 10, 2)->default(0);
                $t->string('currency', 3)->default('BOB');
                $t->unsignedInteger('quantity')->nullable();      // null = sin límite propio
                $t->dateTime('sales_start')->nullable();
                $t->dateTime('sales_end')->nullable();
                $t->unsignedTinyInteger('max_per_order')->default(10);
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index('event_id');
            });
        }

        if (!Schema::hasTable('aero_tickets_guests')) {
            Schema::create('aero_tickets_guests', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id');
                $t->unsignedInteger('ticket_type_id')->nullable();
                $t->string('name');
                $t->string('email')->nullable();
                $t->string('phone', 30)->nullable();
                $t->unsignedTinyInteger('party_size')->default(1);       // >1 = invitación grupal
                $t->string('token', 40)->unique();
                $t->string('rsvp', 10)->default('pending');              // pending | confirmed | declined
                $t->unsignedTinyInteger('confirmed_count')->default(0);
                $t->timestamp('invited_at')->nullable();
                $t->timestamp('responded_at')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['event_id', 'rsvp']);
            });
        }

        if (!Schema::hasTable('aero_tickets_tickets')) {
            Schema::create('aero_tickets_tickets', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id');
                $t->unsignedInteger('session_id')->nullable();
                $t->unsignedInteger('ticket_type_id')->nullable();
                $t->unsignedInteger('guest_id')->nullable();
                $t->string('code', 16)->unique();
                $t->string('holder_name');
                $t->string('holder_email')->nullable();
                $t->string('holder_phone', 30)->nullable();
                $t->string('holder_document', 30)->nullable();
                $t->string('status', 10)->default('valid');              // pending | valid | used | cancelled
                $t->string('source', 12)->default('registration');       // registration | guest | manual
                $t->decimal('amount', 10, 2)->default(0);
                $t->string('currency', 3)->default('BOB');
                $t->timestamp('paid_at')->nullable();
                $t->timestamp('checked_in_at')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['event_id', 'status']);
                $t->index('guest_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['tickets', 'guests', 'ticket_types', 'sessions'] as $t) {
            Schema::dropIfExists('aero_tickets_' . $t);
        }
        if (Schema::hasColumn('aero_tickets_events', 'registration')) {
            Schema::table('aero_tickets_events', fn (Blueprint $t) => $t->dropColumn('registration'));
        }
    }
};
