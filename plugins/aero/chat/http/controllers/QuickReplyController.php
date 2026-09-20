<?php namespace Aero\Chat\Http\Controllers;

use Aero\Crm\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QuickReplyController extends Controller
{
    /** GET quick-replies — respuestas activas del tenant, para el menú "/" y los atajos. */
    public function index(Request $request)
    {
        if (!class_exists(QuickReply::class)) {
            return response()->json(['data' => []]);
        }

        $rows = QuickReply::inScope($this->tenantId($request))->active()
            ->orderBy('sort_order')->orderBy('shortcut')
            ->get(['id', 'shortcut', 'title', 'area', 'body', 'sort_order', 'uses_count']);

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id'         => $r->id,
            'shortcut'   => $r->shortcut,
            'title'      => $r->title,
            'area'       => $r->area,
            'area_label' => $r->area_label,
            'body'       => $r->body,
            'sort_order' => (int) $r->sort_order,
            'uses_count' => (int) $r->uses_count,
        ])->all()]);
    }

    /** POST quick-replies/{id}/use — suma un uso (atómico). */
    public function use(Request $request, int $id)
    {
        $reply = QuickReply::inScope($this->tenantId($request))->find($id);
        if (!$reply) {
            return response()->json(['error' => 'not_found', 'message' => 'Respuesta no encontrada.'], 404);
        }

        $reply->newQuery()->whereKey($reply->id)->increment('uses_count');

        return response()->json(['data' => ['id' => $reply->id, 'uses_count' => (int) $reply->fresh()->uses_count]]);
    }

    protected function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenant_id');
    }
}
