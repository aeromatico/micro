<?php namespace Aero\Livechat\Classes;

use Aero\Livechat\Models\Contact;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Message;

/**
 * Cuando una conversación se cierra SOLA por inactividad (ver
 * AutoFinishInactiveConversations) — no a mano, ni por el agente ni por el
 * visitante — puede ser un lead a medio atender: se abre un ticket de CRM con
 * el historial completo para no perderlo. Un cierre manual (el agente sabe
 * que ya terminó) no abre ticket — ver ConversationLifecycle::finish($auto).
 */
class LeadTicket
{
    public static function openFor(Conversation $conversation): void
    {
        if (!class_exists(\Aero\Crm\Models\Ticket::class)) {
            return;
        }

        $settings = \Aero\Crm\Models\CrmSettings::where('tenant_id', $conversation->tenant_id)->first();
        if (!$settings || !$settings->is_enabled) {
            return;
        }

        $messages = $conversation->messages()->orderBy('id')->get();

        // El visitante nunca llegó a escribir nada: no hay lead que seguir.
        if (!$messages->contains(fn (Message $m) => $m->sender_type === Message::CONTACT)) {
            return;
        }

        $contact = $conversation->contact;
        if (!$contact) {
            return;
        }

        \Aero\Crm\Models\Ticket::create([
            'tenant_id'       => $conversation->tenant_id,
            'department_id'   => self::department($conversation->tenant_id)->id,
            'subject'         => 'Chat sin resolver — ' . $contact->display_name,
            'description'     => self::transcript($messages, $contact),
            'requester_name'  => $contact->name,
            'requester_email' => $contact->email,
            'requester_phone' => $contact->phone,
            'source'          => 'livechat',
        ]);
    }

    /** El tenant puede no tener todavía ningún departamento de CRM configurado. */
    protected static function department(?int $tenantId): \Aero\Crm\Models\Department
    {
        return \Aero\Crm\Models\Department::where('tenant_id', $tenantId)->orderBy('sort_order')->first()
            ?? \Aero\Crm\Models\Department::create(['tenant_id' => $tenantId, 'name' => 'Soporte']);
    }

    protected static function transcript($messages, Contact $contact): string
    {
        $lines = [
            'Conversación de chat web con ' . $contact->display_name
                . ' cerrada automáticamente por inactividad, sin finalizar de ningún lado.',
            '',
        ];

        foreach ($messages as $m) {
            $who = match ($m->sender_type) {
                Message::CONTACT => $contact->display_name,
                Message::AGENT   => $m->agent ? (trim($m->agent->first_name . ' ' . $m->agent->last_name) ?: $m->agent->login) : 'Agente',
                default          => 'Sistema',
            };
            $body = $m->body !== '' ? $m->body : ($m->hasAttachment() ? '[adjunto: ' . $m->attachment_name . ']' : '');
            $lines[] = '[' . $m->created_at->format('d/m H:i') . '] ' . $who . ': ' . $body;
        }

        return implode("\n", $lines);
    }
}
