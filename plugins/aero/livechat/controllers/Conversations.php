<?php namespace Aero\Livechat\Controllers;

use Aero\Livechat\Classes\TenantScope;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Message;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;
use Input;

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

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::AGENT,
            'sender_id'       => BackendAuth::getUser()->id,
            'body'            => $body,
        ]);

        $conversation->last_message_at = now();
        $conversation->agent_unread_count = 0;
        $conversation->visitor_unread_count++;
        $conversation->save();

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
