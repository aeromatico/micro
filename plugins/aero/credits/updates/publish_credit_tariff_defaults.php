<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Corrige la 1.18.0: nada se publica por defecto (is_public = false) y se
 * limpian los valores de relleno ('Otros', 'por uso') que quedaron guardados.
 * Solo se publican funciones con nombre público explícito.
 */
return new class extends Migration
{
    protected const PUBLIC = [
        'hello.message_send' => ['Mensaje de WhatsApp', 'por mensaje'],
        'hello.voice_call'   => ['Llamada de voz con IA por WhatsApp', 'por llamada'],
        'sms.segment'        => ['Mensaje SMS', 'por segmento'],
        'workflows.run'      => ['Ejecución de una automatización', 'por ejecución'],
        'pay.qr_completed'   => ['Cobro por QR completado', 'por QR cobrado'],
        'aifields.complete'  => ['Asistente de IA en formularios', 'por uso'],
        'aifields.code'      => ['Asistente de IA para código y diseño', 'por uso'],
    ];

    public function up()
    {
        if (!Schema::hasColumn('aero_credits_actions', 'is_public')) {
            return;
        }

        Schema::table('aero_credits_actions', fn ($table) => $table->boolean('is_public')->default(false)->change());

        DB::table('aero_credits_actions')->where('category', 'Otros')->update(['category' => null]);
        DB::table('aero_credits_actions')->where('unit', 'por uso')->update(['unit' => null]);
        DB::table('aero_credits_actions')->update(['is_public' => false]);

        foreach (self::PUBLIC as $code => [$label, $unit]) {
            DB::table('aero_credits_actions')->where('code', $code)->update([
                'public_label' => DB::raw("COALESCE(public_label, " . DB::getPdo()->quote($label) . ")"),
                'unit'         => DB::raw("COALESCE(unit, " . DB::getPdo()->quote($unit) . ")"),
                'is_public'    => true,
            ]);
        }
    }

    public function down()
    {
        // Sin rollback: ver CLAUDE.md (nunca plugin:refresh/rollback).
    }
};
