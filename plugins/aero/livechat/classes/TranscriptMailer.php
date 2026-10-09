<?php namespace Aero\Livechat\Classes;

use Aero\Livechat\Models\Contact;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Inbox;
use Aero\Livechat\Models\Message;
use Mail;

/** Envía la transcripción de una conversación al correo del propio visitante. Fase 1: HTML simple, sin plantilla de October Mail. */
class TranscriptMailer
{
    public static function send(Conversation $conversation, Contact $contact, Inbox $inbox): void
    {
        $subject = "Transcripción de tu conversación con {$inbox->name}";
        $html = static::renderHtml($conversation, $contact, $inbox);

        Mail::html($html, function ($message) use ($contact, $subject) {
            $message->to($contact->email, $contact->name ?: null)->subject($subject);
        });
    }

    protected static function renderHtml(Conversation $conversation, Contact $contact, Inbox $inbox): string
    {
        $rows = $conversation->messages()->orderBy('id')->get()->map(function (Message $m) use ($contact) {
            $label = match ($m->sender_type) {
                Message::AGENT  => 'Agente',
                Message::SYSTEM => 'Sistema',
                default         => $contact->name ?: 'Tú',
            };

            $body = $m->hasAttachment()
                ? '📎 <a href="' . e($m->attachment_url) . '">' . e($m->attachment_name) . '</a>'
                : nl2br(e($m->body));

            return '<tr><td style="padding:6px 10px;color:#6b7280;font-size:12px;white-space:nowrap;vertical-align:top;">'
                . e($m->created_at->format('d/m H:i')) . '</td>'
                . '<td style="padding:6px 10px;font-weight:600;vertical-align:top;">' . e($label) . '</td>'
                . '<td style="padding:6px 10px;vertical-align:top;">' . $body . '</td></tr>';
        })->implode('');

        return '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:640px;margin:0 auto;">'
            . '<h2 style="margin:0 0 4px;">' . e($inbox->name) . '</h2>'
            . '<p style="color:#6b7280;margin:0 0 16px;">Transcripción de tu conversación.</p>'
            . '<table style="border-collapse:collapse;width:100%;font-size:13px;">' . $rows . '</table>'
            . '</div>';
    }
}
