<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Motor con cola (columnas de control en deliveries), bandeja in-app y
 * suscripciones Web Push.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_notify_deliveries', function (Blueprint $table) {
            $table->string('dedup_key', 64)->nullable()->after('address');
            $table->string('digest_key', 40)->nullable()->after('dedup_key');
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
            $table->timestamp('scheduled_at')->nullable()->after('sent_at');

            $table->index(['rule_id', 'address', 'created_at'], 'notify_deliv_rule_addr');
            $table->index('dedup_key');
        });

        Schema::create('aero_notify_inbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('tenant_id')->default(0);
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->string('event_code', 120)->nullable();
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'tenant_id', 'created_at']);
        });

        Schema::create('aero_notify_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('tenant_id')->default(0);
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_notify_push_subscriptions');
        Schema::dropIfExists('aero_notify_inbox');

        Schema::table('aero_notify_deliveries', function (Blueprint $table) {
            $table->dropIndex('notify_deliv_rule_addr');
            $table->dropIndex(['dedup_key']);
            $table->dropColumn(['dedup_key', 'digest_key', 'attempts', 'scheduled_at']);
        });
    }
};
