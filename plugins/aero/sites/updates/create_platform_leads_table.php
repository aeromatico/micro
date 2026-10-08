<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_platform_leads', function (Blueprint $table) {
            $table->id();
            $table->string('plan', 30)->comment('trial | enterprise');
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->text('message');
            $table->boolean('is_corporate_email')->default(true);
            $table->string('verification_url')->nullable()->comment('Sitio o red social del negocio, si el email no es corporativo');
            $table->json('metadata')->nullable()->comment('IP, user_agent, page_url');
            $table->enum('status', ['new', 'contacted', 'converted', 'discarded'])->default('new');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['plan', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_platform_leads');
    }
};
