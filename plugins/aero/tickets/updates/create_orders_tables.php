<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_tickets_orders')) {
            Schema::create('aero_tickets_orders', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id');
                $t->unsignedInteger('guest_id')->nullable();
                $t->string('code', 16)->unique();
                $t->string('idempotency_key', 64)->nullable()->unique();   // doble envío del formulario → misma orden
                $t->string('status', 16)->default('pending_payment');      // pending_payment | verifying | confirmed | cancelled | expired | incident
                $t->string('source', 10)->default('public');               // public | guest | manual
                $t->string('buyer_name');
                $t->string('buyer_email')->nullable();
                $t->string('buyer_phone', 30)->nullable();
                $t->string('buyer_document', 30)->nullable();
                $t->text('buyer_extra')->nullable();
                $t->string('currency', 3)->default('BOB');
                $t->decimal('subtotal', 10, 2)->default(0);
                $t->decimal('fees', 10, 2)->default(0);
                $t->decimal('total', 10, 2)->default(0);
                $t->unsignedSmallInteger('quantity')->default(0);
                $t->timestamp('expires_at')->nullable();
                $t->string('payment_reference')->nullable();
                $t->timestamp('verifying_at')->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->string('cancel_reason')->nullable();
                $t->text('incident_note')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['event_id', 'status']);
                $t->index(['status', 'expires_at']);
                $t->index(['event_id', 'buyer_email']);
            });
        }

        if (!Schema::hasTable('aero_tickets_order_items')) {
            Schema::create('aero_tickets_order_items', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('order_id');
                $t->unsignedInteger('ticket_type_id')->nullable();
                $t->string('name');                                         // copia del nombre al comprar
                $t->string('kind', 10)->default('free');
                $t->unsignedSmallInteger('quantity');
                $t->decimal('unit_price', 10, 2)->default(0);               // base
                $t->decimal('unit_fee', 10, 2)->default(0);                 // comisión que paga el comprador
                $t->decimal('subtotal', 10, 2)->default(0);
                $t->decimal('fees', 10, 2)->default(0);
                $t->decimal('total', 10, 2)->default(0);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index('order_id');
            });
        }

        if (!Schema::hasColumn('aero_tickets_tickets', 'order_id')) {
            Schema::table('aero_tickets_tickets', fn (Blueprint $t) => $t->unsignedInteger('order_id')->nullable()->index());
        }

        if (!Schema::hasColumn('aero_tickets_events', 'buyer_extra_label')) {
            Schema::table('aero_tickets_events', function (Blueprint $t) {
                $t->string('buyer_extra_label')->nullable();      // «Información adicional» que pide el evento
                $t->boolean('buyer_extra_required')->default(false);
            });
        }

        $this->backfill();
    }

    /** Las entradas que ya tenían order_code reciben su orden (idempotente). */
    protected function backfill(): void
    {
        $groups = DB::table('aero_tickets_tickets')->whereNull('order_id')->whereNotNull('order_code')->get()->groupBy('order_code');
        foreach ($groups as $code => $rows) {
            if (DB::table('aero_tickets_orders')->where('code', $code)->exists()) {
                continue;
            }
            $first = $rows->first();
            $st = $rows->pluck('status');
            $status = $st->contains('valid') || $st->contains('used') ? 'confirmed'
                : ($st->contains('reserved') ? 'pending_payment' : ($st->contains('pending') ? 'verifying' : ($st->contains('expired') ? 'expired' : 'cancelled')));
            $id = DB::table('aero_tickets_orders')->insertGetId([
                'tenant_id' => $first->tenant_id, 'event_id' => $first->event_id, 'guest_id' => $first->guest_id, 'code' => $code,
                'status' => $status, 'source' => $first->source === 'registration' ? 'public' : $first->source,
                'buyer_name' => preg_replace('/ \(\d+\/\d+\)$/', '', $first->holder_name), 'buyer_email' => $first->holder_email, 'buyer_phone' => $first->holder_phone,
                'currency' => $first->currency, 'subtotal' => $rows->sum('base_amount'), 'fees' => max(0, $rows->sum('amount') - $rows->sum('base_amount')),
                'total' => $rows->sum('amount'), 'quantity' => $rows->count(), 'expires_at' => $first->expires_at,
                'created_at' => $first->created_at, 'updated_at' => now(),
            ]);
            DB::table('aero_tickets_tickets')->where('order_code', $code)->update(['order_id' => $id]);
            foreach ($rows->groupBy('ticket_type_id') as $typeId => $g) {
                $type = $typeId ? DB::table('aero_tickets_ticket_types')->find($typeId) : null;
                DB::table('aero_tickets_order_items')->insert([
                    'tenant_id' => $first->tenant_id, 'order_id' => $id, 'ticket_type_id' => $typeId ?: null, 'name' => $type->name ?? 'Entrada',
                    'kind' => $type->kind ?? 'free', 'quantity' => $g->count(), 'unit_price' => $g->first()->base_amount,
                    'unit_fee' => max(0, $g->first()->amount - $g->first()->base_amount), 'subtotal' => $g->sum('base_amount'),
                    'fees' => max(0, $g->sum('amount') - $g->sum('base_amount')), 'total' => $g->sum('amount'),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('aero_tickets_events', fn (Blueprint $t) => $t->dropColumn(['buyer_extra_label', 'buyer_extra_required']));
        Schema::table('aero_tickets_tickets', fn (Blueprint $t) => $t->dropColumn('order_id'));
        Schema::dropIfExists('aero_tickets_order_items');
        Schema::dropIfExists('aero_tickets_orders');
    }
};
