<?php namespace Aero\Tracking\Http\Controllers\Api;

use Aero\Tracking\Models\Asset;
use Illuminate\Http\Request;

class AssetsController extends ApiController
{
    public function index(Request $request)
    {
        $query = Asset::forTenant($this->tenantId($request))->orderBy('name');

        foreach (['type', 'code'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return $this->paginated($query->paginate(min((int) $request->input('per_page', 50), 200)), fn ($a) => $a->present());
    }

    public function show(Request $request, int $id)
    {
        $asset = Asset::forTenant($this->tenantId($request))->find($id);

        return $asset ? response()->json(['data' => $asset->present(true)]) : $this->notFound('Activo');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:191',
            'type'      => 'nullable|string|max:40',
            'code'      => 'nullable|string|max:60',
            'is_active' => 'nullable|boolean',
            'meta'      => 'nullable|array',
        ]);

        $asset = new Asset($data + ['type' => 'vehicle']);
        $asset->tenant_id = $this->tenantId($request);
        $asset->save();

        return response()->json(['data' => $asset->fresh()->present(true)], 201);
    }

    public function update(Request $request, int $id)
    {
        $asset = Asset::forTenant($this->tenantId($request))->find($id);

        if (!$asset) {
            return $this->notFound('Activo');
        }

        $asset->fill($request->validate([
            'name'      => 'sometimes|required|string|max:191',
            'type'      => 'sometimes|required|string|max:40',
            'code'      => 'nullable|string|max:60',
            'is_active' => 'sometimes|boolean',
            'meta'      => 'nullable|array',
        ]))->save();

        return response()->json(['data' => $asset->present(true)]);
    }

    public function destroy(Request $request, int $id)
    {
        $asset = Asset::forTenant($this->tenantId($request))->find($id);

        if (!$asset) {
            return $this->notFound('Activo');
        }

        $asset->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function regenerateToken(Request $request, int $id)
    {
        $asset = Asset::forTenant($this->tenantId($request))->find($id);

        if (!$asset) {
            return $this->notFound('Activo');
        }

        $asset->regenerateToken();

        return response()->json(['data' => $asset->present(true)]);
    }
}
