<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id sin FK a propósito: el plugin es independiente de Aero.Sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_workflows_workflows')) {
            Schema::create('aero_workflows_workflows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(false);
                $table->string('trigger_type')->default('manual')->comment('manual, event, message, webhook');
                $table->text('trigger_config')->nullable();
                $table->longText('graph')->nullable();
                $table->boolean('expose_as_tool')->default(false)->comment('Disponible para el Super Chatbot IA');
                $table->text('tool_description')->nullable();
                $table->text('tool_schema')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('aero_workflows_runs')) {
            Schema::create('aero_workflows_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('status')->default('queued')->comment('queued, running, waiting, ok, error');
                $table->string('source')->nullable()->comment('manual, event, message, webhook, ai_tool');
                $table->longText('trigger_payload')->nullable();
                $table->longText('context')->nullable();
                $table->longText('result')->nullable();
                $table->unsignedInteger('steps_count')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['workflow_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_workflows_run_steps')) {
            Schema::create('aero_workflows_run_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('run_id');
                $table->string('node_id');
                $table->string('node_type');
                $table->string('status')->default('ok');
                $table->text('input')->nullable();
                $table->text('output')->nullable();
                $table->text('error')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamps();

                $table->index('run_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_workflows_run_steps');
        Schema::dropIfExists('aero_workflows_runs');
        Schema::dropIfExists('aero_workflows_workflows');
    }
};
