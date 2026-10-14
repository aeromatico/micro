<?php namespace Aero\Community\Controllers;

use Aero\Community\Classes\Reporter;
use Aero\Community\Classes\Sla;
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
            'sla_days' => Sla::DAYS,
            'url'      => \Backend::url('aero/community/posts/update/' . $post->id),
        ];
    }

    public function index(): \Illuminate\Http\Response
    {
        return response('', 204);
    }
}
