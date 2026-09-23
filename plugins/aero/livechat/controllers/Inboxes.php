<?php namespace Aero\Livechat\Controllers;

use Aero\Connector\Models\Connector;
use Aero\Livechat\Classes\TelegramBridge;
use Aero\Livechat\Classes\TenantScope;
use Aero\Livechat\Models\Inbox;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Inboxes extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.livechat.manage_inboxes'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Livechat', 'livechat', 'livechat-inboxes');
    }

    public function listExtendQuery($query): void
    {
        $query->inScope(TenantScope::currentTenantId())->withCount('conversations');
    }

    public function formExtendQuery($query): void
    {
        $query->inScope(TenantScope::currentTenantId());
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = TenantScope::currentTenantId();
        }
    }

    /**
     * El dropdown de "Bot de Telegram" lista TODOS los connectors de la
     * plataforma (Aero.Connector no tiene tenant_id) — un tenant no debe
     * verlo nunca, ni el de otros tenants ni el de plataforma. Un tenant
     * conecta su propio bot pegando el token directo (ver formBeforeSave,
     * que crea/actualiza su Connector detrás de escena); la plataforma
     * sigue usando el dropdown como siempre.
     */
    public function formExtendFields($form): void
    {
        if (TenantScope::currentTenantId()) {
            $form->removeField('telegram_connector_id');
        }
        else {
            $form->removeField('telegram_bot_token');
        }
    }

    public function formBeforeSave($model): void
    {
        $token = trim((string) post('Inbox.telegram_bot_token'));
        if ($token === '') {
            return;
        }

        $connector = $model->telegram_connector_id
            ? \Aero\Connector\Models\Connector::find($model->telegram_connector_id)
            : new \Aero\Connector\Models\Connector();

        /**
         * formBeforeSave corre antes de performSaveOnModel (ver
         * FormController::create_onSave/update_onSave) — $model->name
         * todavía no tiene el valor recién tipeado, hay que leerlo del post.
         */
        $name = trim((string) post('Inbox.name')) ?: $model->name;
        $connector->name = 'Telegram – ' . ($name ?: 'Inbox sin nombre');
        $connector->provider_hint = 'telegram';
        $connector->credentials = ['api_key' => $token];
        $connector->save();

        $model->telegram_connector_id = $connector->id;
    }

    public function onConnectTelegram($recordId = null)
    {
        $inbox = Inbox::inScope(TenantScope::currentTenantId())->findOrFail($recordId ?: post('record_id'));

        if (!$inbox->telegram_connector_id) {
            Flash::error('Elegí un bot de Telegram arriba (y guardá) antes de conectar.');
            return;
        }

        $connector = Connector::find($inbox->telegram_connector_id);
        if (!$connector) {
            Flash::error('El bot elegido ya no existe.');
            return;
        }

        if (!$inbox->telegram_chat_id) {
            $chat = TelegramBridge::discoverChatId($connector);
            if (!$chat) {
                Flash::error('No encontré mensajes recientes de ese bot. Escribile algo (o agregalo a un grupo y escribí ahí) y volvé a intentar.');
                return;
            }
            $inbox->telegram_chat_id = (string) $chat['id'];
            $inbox->save();
        }

        $error = null;
        if (TelegramBridge::connect($connector, $error)) {
            Flash::success("Conectado. Chat ID: {$inbox->telegram_chat_id}. Probá escribiendo algo en ese chat.");
        }
        else {
            Flash::error('No se pudo conectar: ' . $error);
        }
    }
}
