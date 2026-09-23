<?php namespace Aero\Livechat\Controllers;

use Aero\Livechat\Classes\AttachmentStorage;
use Aero\Livechat\Classes\TelegramBridge;
use Aero\Livechat\Classes\TenantScope;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Message;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;
use Input;
use Request;

class Conversations extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.livechat.manage_conversations'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Livechat', 'livechat', 'livechat-conversations');
    }

    public function listExtendQuery($query): void
    {
        $query->inScope(TenantScope::currentTenantId());
    }

    public function formExtendQuery($query): void
    {
        $query->inScope(TenantScope::currentTenantId());
    }

    /** El agente abre la conversación: lo que estaba sin leer queda leído. */
    public function update($recordId = null, $context = null)
    {
        $result = parent::update($recordId, $context);

        Conversation::inScope(TenantScope::currentTenantId())
            ->where('id', $recordId)
            ->update(['agent_unread_count' => 0]);

        return $result;
    }

    public function onReply($recordId = null)
    {
        $conversation = Conversation::inScope(TenantScope::currentTenantId())->findOrFail($recordId ?: post('record_id'));
        $body = trim((string) Input::get('reply_body'));

        if ($body === '') {
            Flash::error('Escribe un mensaje antes de enviar.');
            return $this->onLoadMessages($recordId);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::AGENT,
            'sender_id'       => BackendAuth::getUser()->id,
            'body'            => $body,
        ]);

        $conversation->last_message_at = now();
        $conversation->agent_unread_count = 0;
        $conversation->visitor_unread_count++;
        $conversation->save();

        // Mensajes que llegaron VÍA Telegram nunca pasan por acá (los crea
        // TelegramBridge::handleInbound directo) — sin riesgo de eco.
        TelegramBridge::relay($conversation, $message, '👨‍💻 Agente (panel):');

        return $this->onLoadMessages($recordId);
    }

    public function onAttach($recordId = null)
    {
        $conversation = Conversation::inScope(TenantScope::currentTenantId())->findOrFail($recordId ?: post('record_id'));
        $file = Request::file('attachment');

        if (!$file) {
            Flash::error('Elegí un archivo primero.');
            return $this->onLoadMessages($recordId);
        }

        $stored = AttachmentStorage::store($file);
        if (isset($stored['error'])) {
            Flash::error($stored['error']);
            return $this->onLoadMessages($recordId);
        }

        $message = Message::create([
            'conversation_id'  => $conversation->id,
            'sender_type'      => Message::AGENT,
            'sender_id'        => BackendAuth::getUser()->id,
            'body'             => '',
            'attachment_path'  => $stored['path'],
            'attachment_name'  => $stored['name'],
            'attachment_mime'  => $stored['mime'],
            'attachment_size'  => $stored['size'],
            'attachment_token' => $stored['token'],
        ]);

        $conversation->last_message_at = now();
        $conversation->agent_unread_count = 0;
        $conversation->visitor_unread_count++;
        $conversation->save();

        TelegramBridge::relayAttachment($conversation, $message, '👨‍💻 Agente (panel):');

        return $this->onLoadMessages($recordId);
    }

    /** El agente da por terminada la conversación. El visitante puede reabrirla escribiendo de nuevo (ver WidgetController::message). */
    public function onFinish($recordId = null)
    {
        $conversation = Conversation::inScope(TenantScope::currentTenantId())->findOrFail($recordId ?: post('record_id'));

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::SYSTEM,
            'body'            => 'El agente finalizó el chat.',
        ]);

        $conversation->status = Conversation::RESOLVED;
        $conversation->agent_unread_count = 0;
        $conversation->save();

        TelegramBridge::relay($conversation, $message, 'ℹ️');

        Flash::success('Conversación finalizada.');

        return $this->onLoadMessages($recordId);
    }

    /** Refresca solo el log de mensajes — usado por el polling del JS. */
    public function onLoadMessages($recordId = null)
    {
        $this->vars['record'] = Conversation::inScope(TenantScope::currentTenantId())
            ->findOrFail($recordId ?: post('record_id'));

        return ['#livechat-messages-log' => $this->makePartial('messages_log', ['model' => $this->vars['record']])];
    }
}
