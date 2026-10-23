<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_church_audit_logs')) {
            Schema::create('aero_church_audit_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable()->comment('Usuario del panel; null = sistema o formulario público');
                $t->string('user_label', 120)->nullable();
                $t->string('action', 16)->comment('created, updated, deleted, viewed, exported, sent, confirmed, cancelled');
                $t->string('subject_type', 24)->nullable();
                $t->unsignedBigInteger('subject_id')->nullable();
                $t->string('summary', 300);
                $t->text('changes')->nullable()->comment('JSON: solo NOMBRES de campos cambiados, nunca valores');
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['tenant_id', 'subject_type', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_church_audit_logs');
    }
};
