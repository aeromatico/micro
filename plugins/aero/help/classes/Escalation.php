<?php namespace Aero\Help\Classes;

use Aero\Help\Models\Session;
use ApplicationException;

/**
 * Pasa la conversación a una persona usando los mismos canales de soporte que
 * ya existen, sin duplicarlos: un reporte de Aero.Community (que puede seguir
 * en chat con Livechat) o un ticket de la mesa de ayuda (CRM). Va todo el
 * historial y la pantalla de origen para que nadie tenga que repetir nada.
 */
class Escalation
{
    /** @return array<int, array{via: string, label: string, hint: string}> */
    public static function available(): array
    {
        $out = [];

        if (class_exists(\Aero\Community\Classes\Reporter::class)) {
            $out[] = ['via' => 'report', 'label' => 'Reportar el problema', 'hint' => 'Lo recibe el equipo y puedes seguirlo en «Mis reportes».'];
        }
        if (class_exists(\Aero\Community\Classes\TicketBridge::class) && \Aero\Community\Classes\TicketBridge::available()) {
            $out[] = ['via' => 'ticket', 'label' => 'Abrir un ticket', 'hint' => 'Llega a la mesa de ayuda con esta conversación.'];
        }

        return $out;
    }

    public static function run(Session $session, string $via, string $note): array
    {
        if ($session->status === 'escalated') {
            throw new ApplicationException('Esta conversación ya se envió al equipo.');
        }
        if (!in_array($via, array_column(self::available(), 'via'), true)) {
            throw new ApplicationException('Ese canal no está disponible.');
        }

        $summary = self::summary($session, trim($note));
        $context = ['userAgent' => request()->userAgent(), 'title' => 'Ayuda guiada (sesión #' . $session->id . ')'];

        if ($via === 'report') {
            $post = \Aero\Community\Classes\Reporter::create([
                'type'    => 'help',
                'message' => $summary,
                'area'    => $session->area,
                'url'     => $session->url,
                'context' => $context,
            ]);
            $result = [
                'ref'   => 'post:' . $post->id,
                'label' => 'Reporte #' . $post->id,
                'url'   => \Backend::url('aero/community/posts/update/' . $post->id),
                'chat'  => class_exists(\Aero\Community\Classes\ChatBridge::class) ? \Aero\Community\Classes\ChatBridge::forPost($post) : null,
            ];
        } else {
            $department = \Aero\Community\Classes\TicketBridge::departments()[0]['id'] ?? null;
            $ticket = \Aero\Community\Classes\TicketBridge::create([
                'subject'       => self::subject($session, $note),
                'description'   => $summary,
                'department_id' => $department,
                'priority'      => 'normal',
                'url'           => $session->url,
                'area'          => $session->area,
                'context'       => $context,
            ]);
            $result = [
                'ref'   => 'ticket:' . ($ticket->id),
                'label' => 'Ticket #' . ($ticket->number ?? $ticket->id),
                'url'   => \Backend::url('aero/crm/support/view/' . $ticket->id),
                'chat'  => null,
            ];
        }

        $session->status = 'escalated';
        $session->escalated_via = $via;
        $session->escalated_ref = $result['ref'];
        $session->save();

        \Event::fire('aero.help.escalated', [$session, $via]);

        return $result;
    }

    protected static function subject(Session $session, string $note): string
    {
        $first = collect((array) $session->transcript)->firstWhere('r', 'u')['t'] ?? '';

        return mb_substr('Ayuda: ' . ($note !== '' ? $note : ($first !== '' ? $first : ($session->area ?: 'consulta'))), 0, 120);
    }

    protected static function summary(Session $session, string $note): string
    {
        $lines = [];
        if ($note !== '') {
            $lines[] = $note;
            $lines[] = '';
        }
        $lines[] = '— Conversación previa con la ayuda guiada —';
        foreach ((array) $session->transcript as $row) {
            $lines[] = ($row['r'] === 'u' ? 'Persona: ' : 'Ayuda: ') . $row['t'];
        }
        if ($session->flow_id) {
            $lines[] = '';
            $lines[] = 'Flujo: #' . $session->flow_id . ' · respuestas: ' . json_encode(((array) $session->state)['answers'] ?? [], JSON_UNESCAPED_UNICODE);
        }

        return mb_substr(implode("\n", $lines), 0, 7500);
    }
}
