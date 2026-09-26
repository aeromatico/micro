<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_hub_media_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('endpoint_code');
            $table->unsignedBigInteger('credit_transaction_id')->nullable();
            $table->string('status')->default('queued');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_hub_media_jobs');
    }
};
