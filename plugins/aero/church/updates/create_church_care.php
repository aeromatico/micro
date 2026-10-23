<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_church_settings')) {
            Schema::table('aero_church_settings', function (Blueprint $t) {
                if (!Schema::hasColumn('aero_church_settings', 'welcome_enabled')) {
                    $t->boolean('welcome_enabled')->default(false)->comment('Formulario público de bienvenida');
                    $t->boolean('prayer_enabled')->default(false)->comment('Formulario público de oración');
                    $t->text('welcome_text')->nullable();
                    $t->boolean('birthday_enabled')->default(false);
                    $t->text('birthday_template')->nullable();
                    $t->boolean('notify_event_changes')->default(true);
                }
            });
        }

        if (!Schema::hasTable('aero_church_visits')) {
            Schema::create('aero_church_visits', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('member_id')->nullable()->index()->comment('Miembro creado al integrarse');
                $t->unsignedBigInteger('responsible_id')->nullable()->comment('Miembro que lo contacta');
                $t->unsignedBigInteger('interested_group_id')->nullable();
                $t->string('name');
                $t->string('phone', 40)->nullable();
                $t->string('email')->nullable();
                $t->string('how_heard')->nullable();
                $t->text('interest')->nullable();
                $t->boolean('consent_messages')->default(false);
                $t->boolean('consent_data')->default(false);
                $t->date('visit_date');
                $t->string('status', 12)->default('new')->comment('new, contacted, invited, integrated, closed');
                $t->string('source', 10)->default('manual')->comment('manual, qr');
                $t->date('next_action_date')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_visit_contacts')) {
            Schema::create('aero_church_visit_contacts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('visit_id')->index();
                $t->string('channel', 10)->default('whatsapp')->comment('call, whatsapp, visit, other');
                $t->text('note')->nullable();
                $t->string('next_action')->nullable();
                $t->date('next_action_date')->nullable();
                $t->unsignedBigInteger('recorded_by')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_prayers')) {
            Schema::create('aero_church_prayers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('member_id')->nullable()->index();
                $t->unsignedBigInteger('assigned_user_id')->nullable()->index()->comment('Responsable autorizado (usuario del panel)');
                $t->string('name')->nullable();
                $t->string('phone', 40)->nullable();
                $t->text('request');
                $t->boolean('is_private')->default(true);
                $t->boolean('wants_contact')->default(false);
                $t->boolean('consent_data')->default(false);
                $t->string('status', 12)->default('open')->comment('open, in_prayer, answered, closed');
                $t->string('source', 10)->default('manual')->comment('manual, web');
                $t->timestamp('answered_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_prayer_notes')) {
            Schema::create('aero_church_prayer_notes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('prayer_id')->index();
                $t->text('note');
                $t->unsignedBigInteger('author_user_id')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_announcements')) {
            Schema::create('aero_church_announcements', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('title');
                $t->text('body');
                $t->string('kind', 16)->default('announcement')->comment('announcement, reminder, schedule_change, birthday');
                $t->text('audience')->nullable()->comment('JSON: statuses, branch_id, group_id, event_id');
                $t->string('status', 10)->default('draft')->comment('draft, sending, sent');
                $t->unsignedInteger('recipients_count')->default(0);
                $t->unsignedInteger('sent_count')->default(0);
                $t->unsignedInteger('failed_count')->default(0);
                $t->unsignedInteger('skipped_count')->default(0);
                $t->timestamp('sent_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_church_announcement_recipients')) {
            Schema::create('aero_church_announcement_recipients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('announcement_id')->index();
                $t->unsignedBigInteger('member_id')->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('status', 10)->default('sent')->comment('sent, failed');
                $t->string('error')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['announcement_recipients', 'announcements', 'prayer_notes', 'prayers', 'visit_contacts', 'visits'] as $t) {
            Schema::dropIfExists('aero_church_' . $t);
        }
        if (Schema::hasColumn('aero_church_settings', 'welcome_enabled')) {
            Schema::table('aero_church_settings', function (Blueprint $t) {
                $t->dropColumn(['welcome_enabled', 'prayer_enabled', 'welcome_text', 'birthday_enabled', 'birthday_template', 'notify_event_changes']);
            });
        }
    }
};
