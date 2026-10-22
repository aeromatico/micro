<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Portal público: ajustes del portal y de la agenda de visitas, favoritos, búsquedas guardadas y registro de cambios (precio / disponibilidad). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_realestate_settings', 'public_enabled')) {
            Schema::table('aero_realestate_settings', function (Blueprint $t) {
                $t->boolean('public_enabled')->default(false)->comment('Publica el portal /inmuebles');
                $t->string('portal_title')->nullable();
                $t->text('portal_description')->nullable();
                $t->string('whatsapp', 32)->nullable();
                $t->string('phone', 32)->nullable();
                $t->string('email')->nullable();
                $t->string('address')->nullable();
                $t->string('visit_start_time', 5)->default('09:00');
                $t->string('visit_end_time', 5)->default('18:00');
                $t->json('visit_days')->nullable()->comment('Días ISO 1-7 en que se agendan visitas');
                $t->unsignedSmallInteger('visit_notice_hours')->default(4)->comment('Anticipación mínima');
                $t->unsignedSmallInteger('visit_advance_days')->default(30)->comment('Hasta cuántos días adelante se agenda');
            });
        }

        if (!Schema::hasTable('aero_realestate_favorites')) {
            Schema::create('aero_realestate_favorites', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('user_id')->comment('RainLab.User');
                $t->unsignedBigInteger('property_id');
                $t->timestamp('created_at')->nullable();
                $t->unique(['user_id', 'property_id']);
                $t->index(['tenant_id', 'property_id']);
            });
        }

        if (!Schema::hasTable('aero_realestate_saved_searches')) {
            Schema::create('aero_realestate_saved_searches', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('name');
                $t->json('criteria');
                $t->string('email')->nullable();
                $t->string('phone', 32)->nullable();
                $t->boolean('is_active')->default(true);
                $t->string('token', 64)->unique()->comment('Para darse de baja sin iniciar sesión');
                $t->timestamp('last_alert_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('aero_realestate_changes')) {
            Schema::create('aero_realestate_changes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('property_id')->index();
                $t->unsignedBigInteger('offer_id')->nullable();
                $t->string('kind', 12)->comment('price, status');
                $t->string('old_value')->nullable();
                $t->string('new_value')->nullable();
                $t->string('currency', 3)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('notified_at')->nullable();
                $t->index(['notified_at', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['changes', 'saved_searches', 'favorites'] as $table) {
            Schema::dropIfExists('aero_realestate_' . $table);
        }
        if (Schema::hasColumn('aero_realestate_settings', 'public_enabled')) {
            Schema::table('aero_realestate_settings', function (Blueprint $t) {
                $t->dropColumn(['public_enabled', 'portal_title', 'portal_description', 'whatsapp', 'phone', 'email', 'address',
                    'visit_start_time', 'visit_end_time', 'visit_days', 'visit_notice_hours', 'visit_advance_days']);
            });
        }
    }
};
