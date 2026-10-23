<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_church_events')) {
            Schema::create('aero_church_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('group_id')->nullable()->index()->comment('Ministerio organizador');
                $t->unsignedBigInteger('responsible_id')->nullable()->comment('Miembro responsable');
                $t->string('title');
                $t->string('type', 20)->default('service')->comment('service, meeting, retreat, conference, special');
                $t->text('description')->nullable();
                $t->dateTime('starts_at');
                $t->dateTime('ends_at')->nullable();
                $t->string('location')->nullable();
                $t->unsignedInteger('capacity')->nullable()->comment('null = sin límite');
                $t->boolean('registration_open')->default(true);
                $t->unsignedSmallInteger('reminder_hours')->nullable()->comment('Horas antes para el recordatorio; null = sin recordatorio');
                $t->string('status', 12)->default('scheduled')->comment('scheduled, cancelled');
                $t->timestamps();
                $t->index(['tenant_id', 'starts_at']);
            });
        }

        if (!Schema::hasTable('aero_church_event_registrations')) {
            Schema::create('aero_church_event_registrations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('event_id')->index();
                $t->unsignedBigInteger('member_id')->nullable()->index();
                $t->string('guest_name')->nullable();
                $t->string('guest_phone', 40)->nullable();
                $t->string('status', 12)->default('registered')->comment('registered, waitlist, cancelled');
                $t->string('token', 32)->unique()->comment('Contenido del QR personal');
                $t->timestamp('checked_in_at')->nullable();
                $t->timestamp('reminder_sent_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['event_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_church_event_registrations');
        Schema::dropIfExists('aero_church_events');
    }
};
