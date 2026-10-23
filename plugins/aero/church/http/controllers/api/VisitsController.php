<?php namespace Aero\Church\Http\Controllers\Api;

use Aero\Church\Classes\CareService;
use Aero\Church\Classes\ChurchException;
use Aero\Church\Models\Visit;
use Illuminate\Http\Request;

/** Visitantes: la lectura no incluye teléfono ni correo; el alta exige autorización de datos, como el formulario público. */
class VisitsController extends BaseController
{
    public function index(Request $request)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        $q = Visit::forTenant($tenantId)->orderByDesc('id');
        if (in_array($request->query('status'), array_keys(Visit::STATUSES), true)) {
            $q->where('status', $request->query('status'));
        }

        return $this->paginated($q->paginate($this->perPage($request)), fn ($v) => [
            'id' => $v->id, 'name' => $v->name, 'status' => $v->status, 'visit_date' => $v->visit_date?->format('Y-m-d'), 'source' => $v->source,
            'branch_id' => $v->branch_id, 'responsible_id' => $v->responsible_id, 'next_action_date' => $v->next_action_date?->format('Y-m-d'),
        ]);
    }

    public function store(Request $request)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        try {
            $v = app(CareService::class)->registerVisit($tenantId, [
                'name' => (string) $request->input('name'), 'phone' => $request->input('phone'), 'email' => $request->input('email'),
                'how_heard' => $request->input('how_heard'), 'interest' => $request->input('interest'),
                'consent_data' => $request->boolean('consent_data'), 'consent_messages' => $request->boolean('consent_messages'),
            ]);
        } catch (ChurchException $e) {
            return response()->json(['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $v->id, 'status' => $v->status]], 201);
    }
}
