<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_id / user_id son enteros sin FK a propósito (como aero/sms y aero/gym):
 * el plugin no exige que Sites exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_church_branches')) {
            Schema::create('aero_church_branches', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->string('city')->nullable();
                $t->string('address')->nullable();
                $t->string('phone', 40)->nullable();
                $t->boolean('is_main')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_families')) {
            Schema::create('aero_church_families', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->string('address')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_members')) {
            Schema::create('aero_church_members', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('family_id')->nullable()->index();
                $t->unsignedBigInteger('guardian_id')->nullable()->comment('Tutor (otro miembro) si es menor');
                $t->unsignedBigInteger('user_id')->nullable()->comment('Usuario del portal privado');
                $t->string('first_name');
                $t->string('last_name')->nullable();
                $t->string('document', 40)->nullable();
                $t->string('gender', 10)->nullable();
                $t->string('civil_status', 20)->nullable();
                $t->date('birth_date')->nullable();
                $t->date('marriage_date')->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('email')->nullable();
                $t->string('address')->nullable();
                $t->string('status', 20)->default('visitor')->comment('visitor, new, active, inactive');
                $t->string('family_role', 20)->nullable()->comment('head, spouse, child, other');
                $t->date('joined_at')->nullable();
                $t->boolean('baptized')->default(false);
                $t->date('baptism_date')->nullable();
                $t->boolean('consent_messages')->default(false);
                $t->boolean('consent_data')->default(false);
                $t->timestamp('consent_at')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_church_members');
        Schema::dropIfExists('aero_church_families');
        Schema::dropIfExists('aero_church_branches');
    }
};
