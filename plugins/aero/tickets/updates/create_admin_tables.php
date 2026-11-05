<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Organizadores = espacios de trabajo que venden entradas, con su solicitud y decisión.
        if (!Schema::hasTable('aero_tickets_organizers')) {
            Schema::create('aero_tickets_organizers', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->string('status', 10)->default('pending');         // pending | approved | rejected | suspended
                $t->string('legal_name')->nullable();
                $t->string('document', 40)->nullable();                // NIT / CI
                $t->string('contact_name')->nullable();
                $t->string('contact_email')->nullable();
                $t->string('contact_phone', 30)->nullable();
                $t->string('city', 100)->nullable();
                $t->text('description')->nullable();
                $t->timestamp('requested_at')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->unsignedInteger('decided_by')->nullable();
                $t->string('decided_by_name')->nullable();
                $t->string('decision_note', 500)->nullable();
                $t->timestamps();
                $t->index('status');
            });
        }

        // Bitácora de auditoría: solo se agrega.
        if (!Schema::hasTable('aero_tickets_audit')) {
            Schema::create('aero_tickets_audit', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id')->nullable();
                $t->string('subject_type', 30);                        // event | organizer | user | buyer | commission_rule | setting | location | template | order
                $t->unsignedInteger('subject_id')->nullable();
                $t->string('action', 40);
                $t->string('actor_type', 10);                          // admin | system | organizer
                $t->unsignedInteger('actor_id')->nullable();
                $t->string('actor_name')->nullable();
                $t->text('changes')->nullable();                       // {campo: [antes, después]}
                $t->string('note', 500)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['subject_type', 'subject_id', 'id']);
                $t->index(['event_id', 'id']);
                $t->index(['tenant_id', 'id']);
                $t->index(['actor_id', 'id']);
                $t->index('created_at');
            });
        }

        // Reglas de comisión por organizador o por evento (la base de la plataforma vive en Ajustes; el tipo de entrada manda sobre todo).
        if (!Schema::hasTable('aero_tickets_commission_rules')) {
            Schema::create('aero_tickets_commission_rules', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->string('scope', 10);                               // tenant | event
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedInteger('event_id')->nullable();
                $t->decimal('percent', 5, 2)->nullable();              // null = hereda del nivel siguiente
                $t->decimal('fixed', 10, 2)->nullable();
                $t->string('payer', 10)->nullable();                   // buyer | organizer
                $t->boolean('is_active')->default(true);
                $t->date('valid_from')->nullable();
                $t->date('valid_until')->nullable();
                $t->string('note', 500)->nullable();
                $t->string('created_by')->nullable();
                $t->timestamps();
                $t->index(['scope', 'tenant_id', 'event_id']);
            });
        }

        // Libro de comisiones: una fila por orden cobrada (base para liquidaciones futuras).
        if (!Schema::hasTable('aero_tickets_commission_entries')) {
            Schema::create('aero_tickets_commission_entries', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedInteger('event_id');
                $t->unsignedInteger('order_id')->unique();             // una sola vez por orden
                $t->unsignedInteger('payment_id')->nullable();
                $t->string('currency', 3)->default('BOB');
                $t->decimal('base_amount', 10, 2)->default(0);         // ventas base de la orden
                $t->decimal('commission_amount', 10, 2)->default(0);
                $t->string('payer', 10)->nullable();                   // quién la asumió (según las condiciones de la orden)
                $t->text('terms')->nullable();                         // condiciones aplicadas (copia)
                $t->string('status', 10)->default('accrued');          // accrued | void | settled
                $t->unsignedInteger('settlement_id')->nullable();      // reservado: liquidaciones automáticas
                $t->timestamp('accrued_at')->nullable();
                $t->timestamp('voided_at')->nullable();
                $t->string('void_reason')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'status']);
                $t->index(['event_id', 'status']);
                $t->index(['status', 'settlement_id']);
            });
        }

        if (!Schema::hasTable('aero_tickets_locations')) {
            Schema::create('aero_tickets_locations', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->string('type', 10);                                // department | city
                $t->string('name', 100);
                $t->unsignedInteger('parent_id')->nullable();          // departamento de una ciudad
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
                $t->unique(['type', 'parent_id', 'name']);
            });
        }

        if (!Schema::hasTable('aero_tickets_templates')) {
            Schema::create('aero_tickets_templates', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->string('code', 40)->unique();
                $t->string('name');
                $t->string('subject');
                $t->text('body');                                      // HTML con {{variables}}
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        // Compradores (sin cuenta): identidad por correo, con posibilidad de bloqueo.
        if (!Schema::hasTable('aero_tickets_buyers')) {
            Schema::create('aero_tickets_buyers', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->string('email')->unique();
                $t->string('name')->nullable();
                $t->string('phone', 30)->nullable();
                $t->unsignedInteger('orders_count')->default(0);
                $t->timestamp('first_order_at')->nullable();
                $t->timestamp('last_order_at')->nullable();
                $t->string('status', 10)->default('active');           // active | blocked
                $t->string('blocked_reason', 500)->nullable();
                $t->timestamp('blocked_at')->nullable();
                $t->string('blocked_by')->nullable();
                $t->timestamps();
                $t->index('status');
            });
        }

        if (!Schema::hasColumn('aero_tickets_events', 'moderation')) {
            Schema::table('aero_tickets_events', function (Blueprint $t) {
                $t->string('moderation', 10)->default('ok');           // ok | pending | rejected | suspended
                $t->string('moderation_note', 500)->nullable();
                $t->timestamp('moderated_at')->nullable();
                $t->string('moderated_by')->nullable();
                $t->index(['moderation', 'status']);
            });
        }

        if (!Schema::hasColumn('aero_tickets_orders', 'commission_total')) {
            Schema::table('aero_tickets_orders', function (Blueprint $t) {
                $t->decimal('commission_total', 10, 2)->default(0);    // comisión de la plataforma (la pague quien la pague)
                $t->text('commission_terms')->nullable();              // condiciones comerciales aplicadas a ESTA orden
            });
        }

        // Organizadores y compradores que ya existen (idempotente).
        foreach (DB::table('aero_tickets_events')->whereNotNull('tenant_id')->distinct()->pluck('tenant_id') as $tid) {
            if (!DB::table('aero_tickets_organizers')->where('tenant_id', $tid)->exists()) {
                DB::table('aero_tickets_organizers')->insert([
                    'tenant_id' => $tid, 'status' => 'approved', 'requested_at' => now(), 'decided_at' => now(), 'decided_by_name' => 'Sistema (organizador previo)',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        DB::table('aero_tickets_orders')->whereNotNull('buyer_email')->selectRaw('buyer_email e, MAX(buyer_name) n, MAX(buyer_phone) p, COUNT(*) c, MIN(created_at) f, MAX(created_at) l')->groupBy('buyer_email')->get()->each(function ($r) {
            if (!DB::table('aero_tickets_buyers')->where('email', $r->e)->exists()) {
                DB::table('aero_tickets_buyers')->insert(['email' => $r->e, 'name' => $r->n, 'phone' => $r->p, 'orders_count' => $r->c, 'first_order_at' => $r->f, 'last_order_at' => $r->l, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Auditoría y libro de comisiones son evidencia contable: no se borran al desinstalar.
    }
};
