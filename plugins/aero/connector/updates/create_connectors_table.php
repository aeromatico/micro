<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_connector_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('base_url')->nullable();
            $table->text('config')->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->string('owner_type')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_connector_connectors');
    }
};
