<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Redes con OAuth2 renovable (YouTube, X, TikTok): refresh_token por cuenta y
 * llaves de app propias por tenant (opcional; sin ellas se usa la de Market).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_social_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('aero_social_accounts', 'refresh_token')) {
                $table->text('refresh_token')->nullable()->after('access_token'); // cifrado
            }
            if (!Schema::hasColumn('aero_social_accounts', 'app_id')) {
                $table->unsignedBigInteger('app_id')->nullable()->after('parent_id'); // null = app de Market
            }
        });

        if (!Schema::hasTable('aero_social_apps')) {
            Schema::create('aero_social_apps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('network', 20);
                $table->string('client_id');
                $table->text('client_secret');   // cifrado
                $table->timestamps();

                $table->unique(['tenant_id', 'network']);
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_social_apps');
        Schema::table('aero_social_accounts', function (Blueprint $table) {
            foreach (['refresh_token', 'app_id'] as $col) {
                if (Schema::hasColumn('aero_social_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
