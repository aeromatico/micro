<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_connector_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();

            $table->text('secret_encrypted')->nullable();
            $table->string('verification')->default('none');
            $table->string('signature_header')->nullable();
            $table->string('dispatch_event');

            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_connector_webhook_endpoints');
    }
};
