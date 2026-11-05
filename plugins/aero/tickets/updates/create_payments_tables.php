<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_tickets_payment_settings')) {
            Schema::create('aero_tickets_payment_settings', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->boolean('qr_enabled')->default(false);
                $t->unsignedBigInteger('qr_bank_account_id')->nullable();    // cuenta de aero/pay
                $t->boolean('transfer_enabled')->default(false);
                $t->text('transfer_instructions')->nullable();               // banco, cuenta, titular…
                $t->string('proof_policy', 10)->default('optional');         // none | optional | required (comprobante en transferencias)
                $t->text('general_instructions')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_tickets_payments')) {
            Schema::create('aero_tickets_payments', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id');
                $t->unsignedInteger('order_id');
                $t->string('method', 10);                                    // qr | transfer | manual
                $t->string('provider', 30)->nullable();                      // pay_qr | (futuros: tarjeta…)
                $t->string('provider_ref', 80)->nullable();                  // id del intento en el proveedor
                $t->string('status', 16)->default('pending');                // pending | submitted | approved | rejected | cancelled | expired | refund_pending | refunded
                $t->string('currency', 3)->default('BOB');
                $t->decimal('amount', 10, 2);                                // lo que se espera cobrar
                $t->decimal('amount_received', 10, 2)->nullable();           // lo que verificó el administrador / el proveedor
                $t->string('reference')->nullable();                         // referencia de operación informada
                $t->string('sender_name')->nullable();
                $t->boolean('is_late')->default(false);                      // llegó/verificó después de vencer la orden
                $t->boolean('is_suspicious')->default(false);
                $t->string('flag_reason')->nullable();
                $t->string('reject_reason')->nullable();
                $t->unsignedInteger('verified_by')->nullable();              // backend_users.id
                $t->string('verified_by_name')->nullable();
                $t->string('verified_by_actor', 10)->nullable();             // admin | gateway
                $t->string('refund_reference')->nullable();
                $t->timestamp('submitted_at')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->timestamp('refunded_at')->nullable();
                $t->timestamps();
                $t->unique(['provider', 'provider_ref']);                    // un mismo cobro del proveedor no se aplica dos veces
                $t->index(['tenant_id', 'created_at']);
                $t->index(['order_id', 'status']);
                $t->index(['event_id', 'status']);
                $t->index(['tenant_id', 'reference']);
            });
        }

        if (!Schema::hasTable('aero_tickets_payment_proofs')) {
            Schema::create('aero_tickets_payment_proofs', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('payment_id')->index();
                $t->string('mime', 40);
                $t->string('name')->nullable();
                $t->unsignedInteger('size');
                $t->string('sha256', 64)->index();
                $t->longText('content');                                     // cifrado: nada sensible queda en disco público
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        // Historial de solo-agregar: cada paso del pago, quién y cuándo.
        if (!Schema::hasTable('aero_tickets_payment_events')) {
            Schema::create('aero_tickets_payment_events', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('payment_id')->nullable();
                $t->unsignedInteger('order_id')->nullable();
                $t->string('action', 24);
                $t->string('actor_type', 10);                                // buyer | admin | system | gateway
                $t->unsignedInteger('actor_id')->nullable();
                $t->string('actor_name')->nullable();
                $t->string('from_status', 16)->nullable();
                $t->string('to_status', 16)->nullable();
                $t->decimal('amount', 10, 2)->nullable();
                $t->string('note', 500)->nullable();
                $t->text('meta')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['payment_id', 'id']);
                $t->index(['order_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        // Es contabilidad: no se borra el historial financiero al desinstalar.
    }
};
