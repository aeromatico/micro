<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_church_groups')) {
            Schema::create('aero_church_groups', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('backend_user_id')->nullable()->index()->comment('Responsable en el panel (líder)');
                $t->unsignedBigInteger('leader_id')->nullable()->comment('Miembro que lidera el grupo');
                $t->string('name');
                $t->string('type', 20)->default('ministry')->comment('ministry, cell, youth, children, other');
                $t->text('description')->nullable();
                $t->string('meeting_day', 12)->nullable();
                $t->string('meeting_time', 8)->nullable();
                $t->string('location')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_group_members')) {
            Schema::create('aero_church_group_members', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('group_id');
                $t->unsignedBigInteger('member_id');
                $t->string('role', 12)->default('member')->comment('leader, assistant, member');
                $t->date('joined_at')->nullable();
                $t->date('left_at')->nullable();
                $t->timestamps();
                $t->unique(['group_id', 'member_id']);
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_meetings')) {
            Schema::create('aero_church_meetings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('group_id')->index();
                $t->string('title')->nullable();
                $t->date('meeting_date');
                $t->string('start_time', 8)->nullable();
                $t->string('location')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'meeting_date']);
            });
        }

        if (!Schema::hasTable('aero_church_attendances')) {
            Schema::create('aero_church_attendances', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('meeting_id');
                $t->unsignedBigInteger('member_id');
                $t->boolean('present')->default(true);
                $t->timestamps();
                $t->unique(['meeting_id', 'member_id']);
                $t->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_church_attendances');
        Schema::dropIfExists('aero_church_meetings');
        Schema::dropIfExists('aero_church_group_members');
        Schema::dropIfExists('aero_church_groups');
    }
};
