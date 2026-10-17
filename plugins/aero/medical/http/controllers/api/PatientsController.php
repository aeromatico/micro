<?php namespace Aero\Medical\Http\Controllers\Api;

use Aero\Medical\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * API de pacientes: SOLO datos administrativos y solo de lectura. Nunca devuelve alergias, medicación,
 * antecedentes, notas, diagnósticos ni archivos, y una key sin negocio dueño no ve nada (no hay vista global).
 */
class PatientsController extends Controller
{
    public const FIELDS = ['id', 'kind', 'name', 'document', 'birth_date', 'sex', 'species', 'breed', 'microchip', 'is_active', 'customer_id', 'created_at'];

    public function index(Request $request)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        $q = Patient::where('tenant_id', $tenantId)->orderBy('id');
        if (($term = trim((string) $request->query('q'))) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('document', 'like', $like)->orWhere('microchip', 'like', $like));
        }
        if (in_array($request->query('kind'), ['human', 'animal'], true)) {
            $q->where('kind', $request->query('kind'));
        }
        if ($request->query('active') !== null) {
            $q->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $page = $q->paginate(max(1, min(200, (int) $request->query('per_page', 50))));

        return response()->json([
            'data' => collect($page->items())->map(fn ($p) => $this->present($p))->all(),
            'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(Request $request, int $id)
    {
        if (($tenantId = $this->tenantId($request)) === null) {
            return $this->noTenant();
        }

        $p = Patient::where('tenant_id', $tenantId)->find($id);

        return $p
            ? response()->json(['data' => $this->present($p)])
            : response()->json(['error' => 'not_found', 'message' => 'Paciente no encontrado.'], 404);
    }

    protected function present(Patient $p): array
    {
        return collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => $p->{$f} instanceof \DateTimeInterface ? $p->{$f}->format($f === 'created_at' ? 'c' : 'Y-m-d') : $p->{$f}])->all();
    }

    protected function tenantId(Request $request): ?int
    {
        $key = $request->attributes->get('api_key');

        return $key && ($key->owner_type ?? null) === \Aero\Sites\Models\Tenant::class && $key->owner_id ? (int) $key->owner_id : null;
    }

    protected function noTenant()
    {
        return response()->json(['error' => 'tenant_required', 'message' => 'Esta API key no pertenece a un negocio: los datos de pacientes solo se consultan con la key de su negocio.'], 403);
    }
}
