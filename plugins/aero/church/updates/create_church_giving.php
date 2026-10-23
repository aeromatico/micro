<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_church_settings')) {
            Schema::create('aero_church_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->unsignedBigInteger('bank_account_id')->nullable()->comment('Cuenta de aero/pay que emite los QR');
                $t->string('currency', 3)->default('BOB');
                $t->string('receipt_prefix', 10)->default('R');
                $t->unsignedInteger('receipt_counter')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_church_funds')) {
            Schema::create('aero_church_funds', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->string('kind', 12)->default('offering')->comment('tithe, offering, donation, campaign');
                $t->decimal('goal_amount', 12, 2)->nullable();
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_contributions')) {
            Schema::create('aero_church_contributions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('fund_id')->index();
                $t->unsignedBigInteger('member_id')->nullable()->index();
                $t->string('donor_name')->nullable()->comment('Aportante externo; vacío si es miembro o anónimo');
                $t->boolean('is_anonymous')->default(false);
                $t->decimal('amount', 12, 2);
                $t->string('currency', 3)->default('BOB');
                $t->date('date');
                $t->string('method', 10)->default('cash')->comment('cash, transfer, qr');
                $t->string('status', 10)->default('confirmed')->comment('pending, confirmed, cancelled');
                $t->string('reference')->nullable()->comment('Referencia bancaria de la transferencia');
                $t->string('payment_reference')->nullable()->index()->comment('internal_reference del QR de Pay');
                $t->timestamp('confirmed_at')->nullable();
                $t->string('receipt_number', 30)->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('recorded_by')->nullable()->comment('Usuario del panel que lo registró');
                $t->timestamps();
                $t->index(['tenant_id', 'date']);
                $t->index(['tenant_id', 'status']);
                $t->unique(['tenant_id', 'receipt_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_church_contributions');
        Schema::dropIfExists('aero_church_funds');
        Schema::dropIfExists('aero_church_settings');
    }
};
