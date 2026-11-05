<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Dispositivos / personas que validan en puerta, con acceso solo a los eventos asignados.
        if (!Schema::hasTable('aero_tickets_scanners')) {
            Schema::create('aero_tickets_scanners', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id');
                $t->string('name');
                $t->string('token_hash', 64)->unique();          // sha256 del enlace: el enlace en claro no se guarda
                $t->string('token_hint', 8)->nullable();          // últimos caracteres, para reconocerlo
                $t->boolean('is_active')->default(true);
                $t->timestamp('last_seen_at')->nullable();
                $t->string('last_seen_agent', 120)->nullable();
                $t->string('created_by')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('aero_tickets_scanner_events')) {
            Schema::create('aero_tickets_scanner_events', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->unsignedInteger('scanner_id');
                $t->unsignedInteger('event_id');
                $t->primary(['scanner_id', 'event_id']);
                $t->index('event_id');
            });
        }

        // Registro de TODA lectura (válida o no). Solo se agrega.
        if (!Schema::hasTable('aero_tickets_scans')) {
            Schema::create('aero_tickets_scans', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('event_id');
                $t->unsignedInteger('ticket_id')->nullable();
                $t->unsignedInteger('ok_ticket_id')->nullable()->unique();   // solo la lectura que SÍ dio ingreso: la BD impide un segundo
                $t->string('code', 16)->nullable();
                $t->string('holder_name')->nullable();
                $t->string('result', 20);                                     // ok | already_used | invalid | wrong_event | cancelled | expired | not_issued | event_cancelled | event_not_open | not_allowed
                $t->string('method', 10)->default('qr');                      // qr | code | manual
                $t->string('operator_type', 10);                              // scanner | admin
                $t->unsignedInteger('operator_id')->nullable();
                $t->string('operator_name')->nullable();
                $t->string('message')->nullable();
                $t->timestamp('scanned_at')->useCurrent();
                $t->index(['event_id', 'scanned_at']);
                $t->index(['event_id', 'result']);
                $t->index('ticket_id');
                $t->index(['tenant_id', 'scanned_at']);
            });
        }

        if (!Schema::hasColumn('aero_tickets_tickets', 'checked_in_by')) {
            Schema::table('aero_tickets_tickets', fn (Blueprint $t) => $t->string('checked_in_by', 120)->nullable());
        }
    }

    public function down(): void
    {
        // Registro de accesos = evidencia: no se borra al desinstalar.
    }
};
