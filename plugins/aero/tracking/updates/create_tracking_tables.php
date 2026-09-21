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
        Schema::create('aero_tracking_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('type', 40)->default('vehicle')->comment('Libre: motorcycle, truck, person, bus...');
            $table->string('code', 60)->nullable()->comment('Placa o identificador interno');
            $table->boolean('is_active')->default(true);
            $table->string('ingest_token', 64)->unique();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->float('last_speed')->nullable()->comment('m/s');
            $table->float('last_heading')->nullable();
            $table->unsignedTinyInteger('last_battery')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('aero_tracking_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('aero_tracking_assets')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('status', 20)->default('planned')->comment('planned, active, completed, cancelled');
            $table->text('planned_polyline')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('duration_s')->nullable();
            $table->timestamp('eta_at')->nullable();
            $table->timestamp('optimized_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('aero_tracking_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('aero_tracking_assets')->nullOnDelete();
            $table->string('reference')->nullable()->comment('Referencia libre del consumidor');
            $table->string('external_type')->nullable();
            $table->string('external_id')->nullable();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')
                ->comment('pending, assigned, in_progress, completed, cancelled, failed');
            $table->json('meta')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['external_type', 'external_id']);
            $table->index('asset_id');
        });

        Schema::create('aero_tracking_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('job_id')->constrained('aero_tracking_jobs')->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('aero_tracking_routes')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('type', 20)->default('delivery')->comment('pickup, delivery, service');
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->unsignedSmallInteger('service_minutes')->default(0);
            $table->string('status', 20)->default('pending')->comment('pending, arrived, done, skipped, failed');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('proof')->nullable()->comment('Foto, firma o código de entrega');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'sequence']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('aero_tracking_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('aero_tracking_assets')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->float('speed')->nullable()->comment('m/s');
            $table->float('heading')->nullable();
            $table->float('accuracy')->nullable();
            $table->float('altitude')->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['asset_id', 'recorded_at']);
            $table->index('recorded_at');
        });

        Schema::create('aero_tracking_geofences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('shape', 10)->default('circle')->comment('circle, polygon');
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            $table->json('polygon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_tracking_geofences');
        Schema::dropIfExists('aero_tracking_positions');
        Schema::dropIfExists('aero_tracking_stops');
        Schema::dropIfExists('aero_tracking_jobs');
        Schema::dropIfExists('aero_tracking_routes');
        Schema::dropIfExists('aero_tracking_assets');
    }
};
