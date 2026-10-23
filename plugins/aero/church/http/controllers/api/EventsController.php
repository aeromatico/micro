<?php namespace Aero\Church\Http\Controllers\Api;

use Aero\Church\Models\ChurchEvent;
use Illuminate\Http\Request;

class EventsController extends BaseController
{
    public function index(Request $request)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        $q = ChurchEvent::forTenant($tenantId)->orderBy('starts_at');
        if (filter_var($request->query('upcoming'), FILTER_VALIDATE_BOOLEAN)) {
            $q->upcoming();
        }
        if ($request->query('public') !== null) {
            $q->where('is_public', filter_var($request->query('public'), FILTER_VALIDATE_BOOLEAN));
        }

        return $this->paginated($q->paginate($this->perPage($request)), fn ($e) => $this->present($e));
    }

    public function show(Request $request, int $id)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        $e = ChurchEvent::forTenant($tenantId)->find($id);

        return $e ? response()->json(['data' => $this->present($e)]) : $this->notFound('Evento');
    }

    protected function present(ChurchEvent $e): array
    {
        return [
            'id' => $e->id, 'title' => $e->title, 'type' => $e->type, 'description' => $e->description,
            'starts_at' => $e->starts_at?->format('c'), 'ends_at' => $e->ends_at?->format('c'), 'location' => $e->location,
            'capacity' => $e->capacity, 'registered' => $e->seated, 'is_full' => $e->is_full, 'status' => $e->status,
            'registration_open' => $e->isOpenForRegistration(), 'is_public' => (bool) $e->is_public, 'branch_id' => $e->branch_id,
        ];
    }
}
