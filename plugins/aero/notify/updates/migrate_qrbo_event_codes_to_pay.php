<?php

use October\Rain\Database\Updates\Migration;

/**
 * El rename de aero/qrbo a aero/pay (ver EventCatalog::qrbo(), namespace
 * Aero\Qrbo -> Aero\Pay) cambia los 10 códigos 'qrbo.*' del catálogo a
 * 'pay.*' y su source_plugin a 'Aero.Pay'. Los códigos ya están sembrados en
 * aero_notify_events (con Rules ya generadas por evento, algunas globales
 * desde la instalación) y aero_notify_templates trae 2 filas con el código
 * viejo incrustado en su propio `code` (qrbo.qr.generated.<canal>.<locale>)
 * para el único evento realmente disparado hoy (QrCodesController::fireAlert).
 * Las Rules no se tocan: referencian el evento por `event_id` (FK), no por
 * código — sobreviven el rename solas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Db::table('aero_notify_events')
            ->where('code', 'like', 'qrbo.%')
            ->update([
                'code'          => Db::raw("REPLACE(code, 'qrbo.', 'pay.')"),
                'source_plugin' => 'Aero.Pay',
            ]);

        Db::table('aero_notify_templates')
            ->where('code', 'like', 'qrbo.%')
            ->update(['code' => Db::raw("REPLACE(code, 'qrbo.', 'pay.')")]);
    }

    public function down(): void
    {
        Db::table('aero_notify_events')
            ->where('source_plugin', 'Aero.Pay')
            ->where('code', 'like', 'pay.%')
            ->update([
                'code'          => Db::raw("REPLACE(code, 'pay.', 'qrbo.')"),
                'source_plugin' => 'Aero.Qrbo',
            ]);

        Db::table('aero_notify_templates')
            ->where('code', 'like', 'pay.qr.generated%')
            ->orWhere('code', 'like', 'pay.payment.%')
            ->orWhere('code', 'like', 'pay.charge.%')
            ->orWhere('code', 'like', 'pay.subscription.%')
            ->orWhere('code', 'like', 'pay.quota.%')
            ->orWhere('code', 'like', 'pay.billing.%')
            ->update(['code' => Db::raw("REPLACE(code, 'pay.', 'qrbo.')")]);
    }
};
