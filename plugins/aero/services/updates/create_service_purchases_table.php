<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_services_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->foreignId('service_id')->constrained('aero_services_services')->cascadeOnDelete();
            // Índice del plan dentro de Service.plans al momento de comprar. El
            // detalle real de lo comprado (nombre, precio, moneda) vive en
            // plan_snapshot: si el catálogo cambia después, esta compra no se altera.
            $table->unsignedSmallInteger('plan_index');
            $table->json('plan_snapshot');
            $table->string('payment_method', 10);                      // credits | money
            $table->string('credit_type_code', 50)->nullable();
            $table->unsignedBigInteger('amount');                      // credits: unidades de esa moneda; money: unidades Aero\Credits\Classes\Money (1 Bs = 10000)
            $table->unsignedInteger('credit_transaction_id')->nullable(); // aero_credits_transactions.id (soft link, Aero.Credits es opcional)
            $table->string('status', 20)->default('pending');          // pending | fulfilled | cancelled
            $table->unsignedInteger('requested_by')->nullable();       // backend_users.id
            $table->unsignedInteger('fulfilled_by')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_services_purchases');
    }
};
