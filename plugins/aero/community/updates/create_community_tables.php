<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / user_id son enteros sin FK a propósito: el plugin es
 * independiente de Aero.Sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_community_posts')) {
            Schema::create('aero_community_posts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type', 20)->default('discussion')->comment('discussion, question, idea, bug, help');
                $table->string('visibility', 10)->default('public')->comment('public (toda la comunidad) o private (solo su tenant y el equipo)');
                $table->string('status', 20)->default('open')->comment('open, reviewing, planned, in_progress, shipped, declined, answered, closed');
                $table->string('title');
                $table->text('body');
                $table->string('area')->nullable()->comment('Rubro o pantalla a la que se refiere');
                $table->text('official_response')->nullable();
                $table->unsignedBigInteger('responded_by')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('sla_due_at')->nullable();
                $table->timestamp('overdue_notified_at')->nullable();
                $table->unsignedInteger('votes_count')->default(0);
                $table->unsignedInteger('comments_count')->default(0);
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->string('page_url', 1000)->nullable();
                $table->string('screenshot')->nullable();
                $table->text('context')->nullable()->comment('JSON: título de página, navegador, errores JS recientes');
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                $table->index(['visibility', 'type', 'status']);
                $table->index(['tenant_id', 'created_at']);
                $table->index(['responded_at', 'sla_due_at']);
            });
        }

        if (!Schema::hasTable('aero_community_comments')) {
            Schema::create('aero_community_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('post_id')->index();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('body');
                $table->boolean('is_official')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('aero_community_votes')) {
            Schema::create('aero_community_votes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();

                $table->unique(['post_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_community_votes');
        Schema::dropIfExists('aero_community_comments');
        Schema::dropIfExists('aero_community_posts');
    }
};
