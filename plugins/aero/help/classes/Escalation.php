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
        $result = self::submit($via, [
            'message' => $summary,
            'subject' => self::subject($session, $note),
            'url'     => $session->url,
            'area'    => $session->area,
            'title'   => 'Ayuda guiada (sesión #' . $session->id . ')',
        ]);

        $session->status = 'escalated';
        $session->escalated_via = $via;
        $session->escalated_ref = $result['ref'];
        $session->save();

        \Event::fire('aero.help.escalated', [$session, $via]);

        return $result;
    }

    /**
     * Reporte o ticket directo desde la barra superior del panel, sin pasar por
     * la conversación. Si hay una sesión abierta y es de quien envía, se enlaza.
     */
    public static function quick(string $via, string $message, array $opts = [], ?Session $session = null): array
    {
        if (!in_array($via, array_column(self::available(), 'via'), true)) {
            throw new ApplicationException('Ese canal no está disponible.');
        }
        $message = trim($message);
        if (mb_strlen($message) < 5) {
            throw new ApplicationException('Cuéntanos un poco más (mínimo 5 caracteres).');
        }

        $result = self::submit($via, [
            'message'  => $message,
            'subject'  => mb_substr('Ayuda: ' . preg_replace('/\s+/', ' ', $message), 0, 120),
            'url'      => $opts['url'] ?? ($session->url ?? null),
            'area'     => $opts['area'] ?? ($session->area ?? null),
            'type'     => $opts['type'] ?? 'bug',
            'priority' => $opts['priority'] ?? 'normal',
            'title'    => 'Ayuda guiada (reporte directo)',
        ]);

        if ($session && $session->status !== 'escalated') {
            $session->status = 'escalated';
            $session->escalated_via = $via;
            $session->escalated_ref = $result['ref'];
            $session->save();
        }
        \Event::fire('aero.help.escalated', [$session, $via]);

        return $result;
    }

    /** Crea el reporte de Community o el ticket de CRM y devuelve cómo verlo. */
    protected static function submit(string $via, array $d): array
    {
        $context = ['userAgent' => request()->userAgent(), 'title' => $d['title'] ?? 'Ayuda guiada'];

        if ($via === 'report') {
            $post = \Aero\Community\Classes\Reporter::create([
                'type'    => $d['type'] ?? 'help',
                'message' => $d['message'],
                'area'    => $d['area'] ?? null,
                'url'     => $d['url'] ?? null,
                'context' => $context,
            ]);

            return [
                'ref'   => 'post:' . $post->id,
                'label' => 'Reporte #' . $post->id,
                'url'   => \Backend::url('aero/community/posts/update/' . $post->id),
                'chat'  => class_exists(\Aero\Community\Classes\ChatBridge::class) ? \Aero\Community\Classes\ChatBridge::forPost($post) : null,
            ];
        }

        $ticket = \Aero\Community\Classes\TicketBridge::create([
            'subject'       => $d['subject'],
            'description'   => $d['message'],
            'department_id' => \Aero\Community\Classes\TicketBridge::departments()[0]['id'] ?? null,
            'priority'      => $d['priority'] ?? 'normal',
            'url'           => $d['url'] ?? null,
            'area'          => $d['area'] ?? null,
            'context'       => $context,
        ]);

        return [
            'ref'   => 'ticket:' . $ticket->id,
            'label' => 'Ticket #' . ltrim((string) ($ticket->number ?? $ticket->id), '#'),
            'url'   => \Backend::url('aero/crm/support/view/' . $ticket->id),
            'chat'  => null,
        ];
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
