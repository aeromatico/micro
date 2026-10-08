<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_oauth_identities')) {
            return;
        }

        Schema::create('aero_oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('backend_user_id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('provider', 32);
            $table->string('provider_user_id', 191);
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->text('access_token')->nullable();   // cifrado (Crypt)
            $table->text('refresh_token')->nullable();  // cifrado (Crypt)
            $table->timestamp('token_expires_at')->nullable();
            $table->text('granted_scopes')->nullable(); // json
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['backend_user_id', 'provider']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_oauth_identities');
    }
};
