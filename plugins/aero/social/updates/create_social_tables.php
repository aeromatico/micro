<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_social_accounts')) {
            Schema::create('aero_social_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('network', 20);              // facebook | instagram | …
                $table->string('external_id', 64);
                $table->string('name')->nullable();
                $table->string('username')->nullable();
                $table->string('avatar_url', 1024)->nullable();
                $table->text('access_token')->nullable();   // cifrado (Encryptable)
                $table->timestamp('token_expires_at')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable(); // IG → página de FB que lo conecta
                $table->string('status', 20)->default('active');     // active | expired | disabled
                $table->text('last_error')->nullable();
                $table->unsignedInteger('connected_by')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'network', 'external_id'], 'aero_social_accounts_unique');
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_social_posts')) {
            Schema::create('aero_social_posts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->text('content')->nullable();
                $table->string('link', 1024)->nullable();
                $table->text('account_ids')->nullable();     // jsonable: cuentas elegidas
                $table->string('status', 20)->default('draft'); // draft|scheduled|publishing|published|partial|failed|cancelled
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['status', 'scheduled_at']);
            });
        }

        if (!Schema::hasTable('aero_social_post_targets')) {
            Schema::create('aero_social_post_targets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('account_id');
                $table->string('network', 20);
                $table->string('status', 20)->default('queued'); // queued|publishing|processing|published|failed|cancelled
                $table->timestamp('run_at')->nullable();
                $table->string('external_id', 128)->nullable();
                $table->string('permalink', 1024)->nullable();
                $table->text('error')->nullable();
                $table->text('state')->nullable();           // jsonable: pasos intermedios (contenedor IG…)
                $table->unsignedInteger('checks')->default(0);
                $table->unsignedInteger('credits_charged')->default(0);
                $table->unsignedBigInteger('credit_transaction_id')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['status', 'run_at']);
                $table->index('post_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_social_post_targets');
        Schema::dropIfExists('aero_social_posts');
        Schema::dropIfExists('aero_social_accounts');
    }
};
