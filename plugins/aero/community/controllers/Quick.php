<?php namespace Aero\Community\Controllers;

use Aero\Community\Classes\Reporter;
use Backend\Classes\Controller;
use Request;

/**
 * Solo expone el handler AJAX del botón de soporte rápido; sin menú ni vistas.
 */
class Quick extends Controller
{
    public $requiredPermissions = ['aero.community.use', 'aero.community.superadmin'];

    public function onReport(): array
    {
        $post = Reporter::create([
            'type'    => post('type'),
            'message' => post('message'),
            'area'    => post('area'),
            'url'     => post('url'),
            'share'   => post('share'),
            'shot'    => post('shot'),
            'context' => json_decode((string) post('context'), true) ?: [],
        ]);

        return [
            'ok'       => true,
            'id'       => $post->id,
            'ticket'   => $post->id,
            'url'      => \Backend::url('aero/community/posts/update/' . $post->id),
            'chat'     => \Aero\Community\Classes\ChatBridge::forPost($post),
        ];
    }

    /** Departamentos de la mesa de ayuda para el formulario de ticket. */
    public function onTicketOptions(): array
    {
        return ['departments' => \Aero\Community\Classes\TicketBridge::departments()];
    }

    public function onTicket(): array
    {
        $ticket = \Aero\Community\Classes\TicketBridge::create([
            'subject'       => post('subject'),
            'description'   => post('description'),
            'department_id' => post('department_id'),
            'priority'      => post('priority'),
            'url'           => post('url'),
            'area'          => post('area'),
            'context'       => json_decode((string) post('context'), true) ?: [],
        ]);

        return [
            'ok'     => true,
            'ticket' => $ticket->number ?? $ticket->id,
            'url'    => \Backend::url('aero/crm/support/view/' . $ticket->id),
        ];
    }

    public function index(): \Illuminate\Http\Response
    {
        return response('', 204);
    }
}
