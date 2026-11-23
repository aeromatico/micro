<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Anuncios: cuentas publicitarias, campañas, conjuntos y métricas diarias. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_social_ad_accounts')) {
            Schema::create('aero_social_ad_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('platform', 20);              // meta | tiktok
                $table->string('external_id', 64);           // act_123…
                $table->string('name')->nullable();
                $table->string('currency', 8)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->integer('account_status')->nullable(); // 1 = activa en Meta
                $table->text('access_token')->nullable();    // cifrado
                $table->timestamp('token_expires_at')->nullable();
                $table->string('status', 20)->default('active'); // active | expired | disabled
                $table->text('last_error')->nullable();
                $table->unsignedInteger('connected_by')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'platform', 'external_id'], 'aero_social_ad_accounts_unique');
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_social_campaigns')) {
            Schema::create('aero_social_campaigns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('ad_account_id');
                $table->string('external_id', 64);
                $table->string('name')->nullable();
                $table->string('objective', 64)->nullable();
                $table->string('status', 32)->nullable();          // ACTIVE | PAUSED | …
                $table->string('effective_status', 32)->nullable();
                $table->bigInteger('daily_budget')->nullable();    // unidad mínima de la moneda
                $table->bigInteger('lifetime_budget')->nullable();
                $table->timestamp('start_time')->nullable();
                $table->timestamp('stop_time')->nullable();
                $table->unsignedBigInteger('post_target_id')->nullable(); // si nació de «Promocionar»
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['ad_account_id', 'external_id']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_social_adsets')) {
            Schema::create('aero_social_adsets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('campaign_id');
                $table->string('external_id', 64);
                $table->string('name')->nullable();
                $table->string('status', 32)->nullable();
                $table->string('effective_status', 32)->nullable();
                $table->bigInteger('daily_budget')->nullable();
                $table->bigInteger('lifetime_budget')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'external_id']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_social_insights')) {
            Schema::create('aero_social_insights', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('campaign_id');
                $table->date('date');
                $table->decimal('spend', 14, 2)->default(0);       // moneda de la cuenta
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('reach')->default(0);
                $table->unsignedBigInteger('clicks')->default(0);
                $table->timestamps();

                $table->unique(['campaign_id', 'date']);
                $table->index(['tenant_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_social_insights');
        Schema::dropIfExists('aero_social_adsets');
        Schema::dropIfExists('aero_social_campaigns');
        Schema::dropIfExists('aero_social_ad_accounts');
    }
};
