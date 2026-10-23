<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_church_settings') && !Schema::hasColumn('aero_church_settings', 'site_enabled')) {
            Schema::table('aero_church_settings', function (Blueprint $t) {
                $t->boolean('site_enabled')->default(false)->comment('Sitio público (predicaciones, noticias, eventos, en vivo)');
                $t->boolean('portal_enabled')->default(false)->comment('Portal privado del miembro');
                $t->string('live_url')->nullable();
                $t->string('live_title')->nullable();
                $t->text('live_schedule')->nullable();
            });
        }

        if (Schema::hasTable('aero_church_events') && !Schema::hasColumn('aero_church_events', 'is_public')) {
            Schema::table('aero_church_events', function (Blueprint $t) {
                $t->boolean('is_public')->default(false)->comment('Se muestra e inscribe desde el sitio público');
            });
        }

        if (!Schema::hasTable('aero_church_series')) {
            Schema::create('aero_church_series', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->text('description')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_sermons')) {
            Schema::create('aero_church_sermons', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('series_id')->nullable()->index();
                $t->string('title');
                $t->string('slug');
                $t->string('preacher')->nullable();
                $t->string('topic')->nullable();
                $t->date('preached_on')->nullable();
                $t->text('summary')->nullable();
                $t->string('media_url', 500)->nullable()->comment('Video: YouTube, Facebook o Vimeo');
                $t->string('audio_url', 500)->nullable();
                $t->string('visibility', 8)->default('public')->comment('public, members');
                $t->boolean('is_published')->default(false);
                $t->timestamps();
                $t->unique(['tenant_id', 'slug']);
                $t->index(['tenant_id', 'preached_on']);
            });
        }

        if (!Schema::hasTable('aero_church_resources')) {
            Schema::create('aero_church_resources', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('sermon_id')->nullable()->index();
                $t->string('title');
                $t->text('description')->nullable();
                $t->string('kind', 12)->default('study')->comment('study, group_guide, ministry');
                $t->string('visibility', 8)->default('members')->comment('public, members');
                $t->boolean('is_published')->default(false);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_posts')) {
            Schema::create('aero_church_posts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('title');
                $t->string('slug');
                $t->text('body');
                $t->string('kind', 8)->default('news')->comment('news, notice');
                $t->string('visibility', 8)->default('public')->comment('public, members');
                $t->boolean('is_published')->default(false);
                $t->boolean('is_pinned')->default(false);
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
                $t->unique(['tenant_id', 'slug']);
                $t->index(['tenant_id', 'published_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'resources', 'sermons', 'series'] as $t) {
            Schema::dropIfExists('aero_church_' . $t);
        }
        if (Schema::hasColumn('aero_church_events', 'is_public')) {
            Schema::table('aero_church_events', fn (Blueprint $t) => $t->dropColumn('is_public'));
        }
        if (Schema::hasColumn('aero_church_settings', 'site_enabled')) {
            Schema::table('aero_church_settings', fn (Blueprint $t) => $t->dropColumn(['site_enabled', 'portal_enabled', 'live_url', 'live_title', 'live_schedule']));
        }
    }
};
