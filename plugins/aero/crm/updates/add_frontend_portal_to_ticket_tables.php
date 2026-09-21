<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Portal de soporte en el micrositio del tenant. A diferencia de la mesa de
 * ayuda del panel (agentes de backend), aquí el solicitante es un usuario de
 * rainlab:user o un invitado.
 *
 * - tickets.frontend_user_id: usuario registrado que abrió el ticket (NULL = invitado).
 * - tickets.access_token: enlace privado para que un invitado siga su ticket
 *   sin cuenta; se conserva al reclamar el ticket.
 * - tickets.unread_for_agent / last_customer_reply_at: para resaltar respuestas
 *   del cliente en el panel.
 * - ticket_replies.author_type: agent | customer | system; frontend_user_id y
 *   author_name identifican al autor cuando no es un agente de backend.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_crm_tickets', 'frontend_user_id')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('frontend_user_id')->nullable()->after('contact_id')->index();
            });
        }

        if (!Schema::hasColumn('aero_crm_tickets', 'access_token')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->string('access_token', 64)->nullable()->unique()->after('source');
            });
        }

        if (!Schema::hasColumn('aero_crm_tickets', 'unread_for_agent')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->boolean('unread_for_agent')->default(false)->after('source');
            });
        }

        if (!Schema::hasColumn('aero_crm_tickets', 'last_customer_reply_at')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->timestamp('last_customer_reply_at')->nullable()->after('first_response_at');
            });
        }

        if (!Schema::hasColumn('aero_crm_ticket_replies', 'author_type')) {
            Schema::table('aero_crm_ticket_replies', function (Blueprint $table) {
                $table->string('author_type', 10)->default('agent')->after('user_id');
            });
        }

        if (!Schema::hasColumn('aero_crm_ticket_replies', 'frontend_user_id')) {
            Schema::table('aero_crm_ticket_replies', function (Blueprint $table) {
                $table->unsignedBigInteger('frontend_user_id')->nullable()->after('user_id')->index();
            });
        }

        if (!Schema::hasColumn('aero_crm_ticket_replies', 'author_name')) {
            Schema::table('aero_crm_ticket_replies', function (Blueprint $table) {
                $table->string('author_name')->nullable()->after('author_type');
            });
        }

        // Tickets web existentes sin autor_type coherente (por si en el futuro
        // se marcan manualmente): las respuestas de backend quedan como 'agent'.
        \Db::table('aero_crm_ticket_replies')->whereNull('author_type')->update(['author_type' => 'agent']);
    }

    public function down(): void
    {
        foreach ([
            'aero_crm_ticket_replies' => ['author_name', 'frontend_user_id', 'author_type'],
            'aero_crm_tickets' => ['last_customer_reply_at', 'unread_for_agent', 'access_token', 'frontend_user_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($column) {
                        $blueprint->dropColumn($column);
                    });
                }
            }
        }
    }
};
