<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Un Topic (hilo) de Telegram por conversación, dentro del mismo grupo —
 * evita que varios chats simultáneos se mezclen en un solo hilo de mensajes.
 * NULL = todavía no se intentó crear; 0 = se intentó y el chat no es un
 * grupo "Foro" (Telegram no soporta topics ahí, se sigue usando "reply" para
 * agrupar); > 0 = message_thread_id real del topic ya creado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_livechat_conversations', function (Blueprint $table) {
            $table->integer('telegram_thread_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('aero_livechat_conversations', function (Blueprint $table) {
            $table->dropColumn('telegram_thread_id');
        });
    }
};
