<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_finance_petty_funds')) {
            Schema::create('aero_finance_petty_funds', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name', 120);
                $t->string('custodian', 120)->nullable();
                $t->decimal('imprest_amount', 14, 2)->nullable(); // monto fijo de reposición (opcional)
                $t->unsignedBigInteger('account_id')->nullable(); // su cuenta de Activo en el plan de cuentas
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_finance_petty_operations')) {
            Schema::create('aero_finance_petty_operations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('fund_id');
                $t->string('kind', 8); // fund|return|count
                $t->date('date');
                $t->decimal('amount', 14, 2);
                $t->decimal('difference', 14, 2)->default(0); // arqueo: contado − libro
                $t->unsignedBigInteger('counter_account_id')->nullable(); // de dónde sale / a dónde vuelve el efectivo
                $t->string('description', 255)->nullable();
                $t->string('status', 10)->default('posted');
                $t->unsignedBigInteger('entry_id')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index('fund_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_finance_petty_operations');
        Schema::dropIfExists('aero_finance_petty_funds');
    }
};
