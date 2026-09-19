<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / api_key_id son enteros sin FK a propósito: el plugin es
 * independiente y no debe exigir que Aero.Sites o Aero.Api existan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('aero_sms_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->string('consumer')->nullable()->comment('Etiqueta de quién lo envió: key o usuario');
            $table->string('status')->default('running')->comment('scheduled, running, completed, cancelled');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('credits_charged')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('aero_sms_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('batch_id')->nullable()->constrained('aero_sms_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->string('consumer')->nullable();
            $table->string('reference')->nullable()->comment('Referencia libre del consumidor');
            $table->string('to', 20);
            $table->text('body');
            $table->unsignedTinyInteger('segments')->default(1);
            $table->string('encoding', 8)->default('GSM-7');
            $table->string('status')->default('queued')
                ->comment('queued, sending, sent, delivered, failed, undelivered, blocked, cancelled');
            $table->string('driver', 20)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('error_code', 20)->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('credits_charged')->default(0);
            $table->unsignedBigInteger('credit_transaction_id')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['api_key_id', 'created_at']);
            $table->index('status');
            $table->index('provider_message_id');
            $table->index(['tenant_id', 'reference']);
        });

        Schema::create('aero_sms_optouts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('source')->default('manual');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sms_optouts');
        Schema::dropIfExists('aero_sms_messages');
        Schema::dropIfExists('aero_sms_batches');
        Schema::dropIfExists('aero_sms_templates');
    }
};
