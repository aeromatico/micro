<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Seguimiento de enlaces públicos de terceros (PedidosYa, Yango, inDrive...).
 * Cada feed crea un Job + un Asset virtual, así que el mapa, la API y los
 * eventos aero.tracking.* funcionan igual. tenant_id sin FK, como el resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_tracking_feeds')) {
            Schema::create('aero_tracking_feeds', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('job_id')->nullable();
                $table->unsignedBigInteger('asset_id')->nullable();
                $table->string('provider', 30);
                $table->text('url');
                $table->string('external_id')->nullable();
                $table->string('label')->nullable();
                $table->string('status', 20)->default('active')->comment('active, completed, cancelled, expired, failed, stopped');
                $table->string('phase', 30)->nullable()->comment('queued, confirmed, at_origin, on_the_way, delivered, cancelled');
                $table->string('eta_text')->nullable();
                $table->string('delay_label')->nullable();
                $table->string('message')->nullable();
                $table->text('session_state')->nullable()->comment('Cookies de sesión del proveedor, cifradas');
                $table->unsignedSmallInteger('poll_seconds')->default(10);
                $table->timestamp('next_poll_at')->nullable();
                $table->timestamp('last_polled_at')->nullable();
                $table->unsignedSmallInteger('error_count')->default(0);
                $table->string('last_error')->nullable();
                $table->json('state')->nullable()->comment('Última instantánea normalizada');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['status', 'next_poll_at']);
                $table->index('job_id');
            });
        }

        if (!Schema::hasTable('aero_tracking_feed_events')) {
            Schema::create('aero_tracking_feed_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('feed_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('type', 30);
                $table->json('data')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamp('created_at')->nullable();

                $table->index(['feed_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_tracking_feed_events');
        Schema::dropIfExists('aero_tracking_feeds');
    }
};
