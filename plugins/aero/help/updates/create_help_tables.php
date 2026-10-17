<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_help_flows')) {
            Schema::create('aero_help_flows', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title');
                $table->string('plugin', 60)->nullable()->index();
                $table->text('intro')->nullable();
                $table->longText('steps')->nullable();
                $table->boolean('is_published')->default(true);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('aero_help_topics')) {
            Schema::create('aero_help_topics', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->text('keywords')->nullable();
                $table->string('plugin', 60)->nullable()->index();
                $table->text('screens')->nullable();
                $table->unsignedBigInteger('article_id')->nullable();
                $table->unsignedBigInteger('guide_id')->nullable();
                $table->unsignedBigInteger('flow_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_published')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('aero_help_sessions')) {
            Schema::create('aero_help_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('flow_id')->nullable();
                $table->unsignedBigInteger('topic_id')->nullable();
                $table->string('area', 150)->nullable();
                $table->string('url', 1000)->nullable();
                $table->string('status', 20)->default('open')->index();
                $table->text('state')->nullable();
                $table->longText('transcript')->nullable();
                $table->string('escalated_via', 20)->nullable();
                $table->string('escalated_ref', 60)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_help_searches')) {
            Schema::create('aero_help_searches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('query');
                $table->string('area', 150)->nullable();
                $table->unsignedSmallInteger('results')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->index(['tenant_id', 'created_at']);
                $table->index(['results', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_help_searches');
        Schema::dropIfExists('aero_help_sessions');
        Schema::dropIfExists('aero_help_topics');
        Schema::dropIfExists('aero_help_flows');
    }
};
