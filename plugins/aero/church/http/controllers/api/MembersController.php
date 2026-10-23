<?php namespace Aero\Church\Http\Controllers\Api;

use Aero\Church\Models\Member;
use Illuminate\Http\Request;

/**
 * Miembros: datos básicos. El contacto (teléfono, correo) SOLO sale de adultos que aceptaron recibir mensajes;
 * de un menor nunca sale. Sin documento, dirección, notas ni consentimientos.
 */
class MembersController extends BaseController
{
    public function index(Request $request)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        $q = Member::forTenant($tenantId)->orderBy('id');
        if (($term = trim((string) $request->query('q'))) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like));
        }
        if (in_array($request->query('status'), array_keys(Member::STATUSES), true)) {
            $q->where('status', $request->query('status'));
        }
        if ($request->query('branch_id')) {
            $q->where('branch_id', (int) $request->query('branch_id'));
        }

        return $this->paginated($q->paginate($this->perPage($request)), fn ($m) => $this->present($m));
    }

    public function show(Request $request, int $id)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        $m = Member::forTenant($tenantId)->find($id);

        return $m ? response()->json(['data' => $this->present($m)]) : $this->notFound('Miembro');
    }

    protected function present(Member $m): array
    {
        $out = [
            'id' => $m->id, 'first_name' => $m->first_name, 'last_name' => $m->last_name, 'status' => $m->status,
            'branch_id' => $m->branch_id, 'family_id' => $m->family_id, 'baptized' => (bool) $m->baptized,
            'joined_at' => $m->joined_at?->format('Y-m-d'), 'is_minor' => $m->is_minor, 'created_at' => $m->created_at?->format('c'),
        ];

        if (!$m->is_minor && $m->consent_messages) {
            $out['phone'] = $m->phone;
            $out['email'] = $m->email;
        }

        return $out;
    }
}
