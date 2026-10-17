<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_plan_plans')) {
            Schema::create('aero_plan_plans', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->unique();
                $t->string('status', 20)->default('active');      // active | paused | completed
                $t->timestamp('started_at')->nullable();
                $t->timestamp('paused_at')->nullable();
                $t->unsignedInteger('paused_seconds')->default(0); // pausas ya cerradas
                $t->timestamp('completed_at')->nullable();
                $t->string('leave_reason', 120)->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_plan_steps')) {
            Schema::create('aero_plan_steps', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('plan_id')->index();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedTinyInteger('number');
                $t->string('status', 20)->default('locked');      // locked | open | review | changes | approved
                $t->text('answers')->nullable();                  // json
                $t->timestamp('submitted_at')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamps();
                $t->unique(['plan_id', 'number']);
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_plan_messages')) {
            Schema::create('aero_plan_messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('plan_id')->index();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedTinyInteger('step_number');
                $t->string('author', 10);                         // client | team | system
                $t->unsignedBigInteger('user_id')->nullable();
                $t->text('body');
                $t->string('tag', 20)->nullable();                // changes
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_plan_messages');
        Schema::dropIfExists('aero_plan_steps');
        Schema::dropIfExists('aero_plan_plans');
    }
};
