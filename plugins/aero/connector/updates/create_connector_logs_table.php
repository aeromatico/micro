<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_connector_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['out', 'in']);

            $table->unsignedBigInteger('connector_id')->nullable();
            $table->unsignedBigInteger('webhook_endpoint_id')->nullable();

            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('is_test')->default(false);

            $table->timestamp('created_at')->nullable();

            $table->index('connector_id');
            $table->index('webhook_endpoint_id');
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_connector_logs');
    }
};
