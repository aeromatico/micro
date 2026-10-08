<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_finance_accounts')) {
            Schema::create('aero_finance_accounts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('code', 20);
                $t->string('name', 150);
                $t->string('type', 12); // asset|liability|equity|income|expense
                $t->unsignedBigInteger('parent_id')->nullable();
                $t->string('system_key', 40)->nullable(); // cuentas que usan las integraciones
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->unique(['tenant_id', 'code']);
            });
        }

        if (!Schema::hasTable('aero_finance_journal_entries')) {
            Schema::create('aero_finance_journal_entries', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('number');
                $t->date('date');
                $t->string('description', 255);
                $t->string('status', 10)->default('posted'); // posted|void
                $t->unsignedBigInteger('reversal_of_id')->nullable();
                $t->string('source_type', 40)->nullable();
                $t->string('source_id', 40)->nullable();
                $t->string('source_event', 40)->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->unique(['tenant_id', 'number']);
                $t->unique(['tenant_id', 'source_type', 'source_id', 'source_event'], 'aero_finance_entry_source');
            });
        }

        if (!Schema::hasTable('aero_finance_journal_lines')) {
            Schema::create('aero_finance_journal_lines', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('entry_id');
                $t->unsignedBigInteger('account_id');
                $t->decimal('debit', 14, 2)->default(0);
                $t->decimal('credit', 14, 2)->default(0);
                $t->string('memo', 255)->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index('entry_id');
                $t->index(['tenant_id', 'account_id']);
            });
        }

        if (!Schema::hasTable('aero_finance_movements')) {
            Schema::create('aero_finance_movements', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('kind', 8); // income|expense
                $t->date('date');
                $t->decimal('amount', 14, 2);
                $t->string('currency', 3)->default('BOB');
                $t->decimal('exchange_rate', 12, 6)->default(1);
                $t->unsignedBigInteger('category_account_id');
                $t->unsignedBigInteger('cash_account_id');
                $t->string('description', 255);
                $t->string('counterparty', 150)->nullable();
                $t->string('nit', 30)->nullable();
                $t->string('document_no', 40)->nullable();
                $t->decimal('tax_amount', 14, 2)->default(0);
                $t->string('status', 10)->default('posted'); // posted|void
                $t->unsignedBigInteger('entry_id')->nullable();
                $t->string('source_type', 40)->nullable();
                $t->string('source_id', 40)->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['tenant_id', 'date']);
            });
        }

        if (!Schema::hasTable('aero_finance_settings')) {
            Schema::create('aero_finance_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->unique();
                $t->boolean('auto_post')->default(true);
                $t->boolean('post_shop')->default(true);
                $t->boolean('post_gym')->default(true);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['settings', 'movements', 'journal_lines', 'journal_entries', 'accounts'] as $t) {
            Schema::dropIfExists('aero_finance_' . $t);
        }
    }
};
