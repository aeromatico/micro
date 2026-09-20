<?php

use Aero\Hello\Models\Account;
use Aero\Wapi\Classes\WapiClient;
use Aero\Wapi\Classes\WapiCredentials;
use Aero\Wapi\Classes\WapiInstances;
use October\Rain\Database\Updates\Migration;

/**
 * Las instancias conectadas antes de esta versión solo se suscribieron a
 * message/message_ack/...: los mensajes que el dueño envía desde otro
 * dispositivo (message_create) nunca llegaban. Se re-suscriben, con el mismo
 * método idempotente que usa la conexión (actualiza, no duplica). Si wapi no
 * responde no se aborta la migración: queda registrado y se reintenta
 * reconectando o corriendo `php artisan wapi:resubscribe`… o esta misma línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(Account::class)) {
            return;
        }

        foreach (Account::ofDriver('wapi')->whereNotNull('zernio_account_id')->get() as $account) {
            try {
                (new WapiInstances(new WapiClient(WapiCredentials::apiKey($account->profile))))->registerMessageWebhook($account->zernio_account_id);
            } catch (\Throwable $e) {
                \Log::warning('aero.wapi: no se pudo re-suscribir message_create para la cuenta ' . $account->id . ': ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
    }
};
