<?php namespace Aero\Tracking\Http\Controllers\Api;

use Aero\Tracking\Classes\TrackingService;
use Aero\Tracking\Models\Asset;
use Aero\Tracking\Models\Job;
use Aero\Tracking\Models\Position;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TrackingController extends ApiController
{
    /** Última posición de cada activo activo, con su trabajo abierto (si tiene). */
    public function live(Request $request)
    {
        $tenantId = $this->tenantId($request);

        $assets = Asset::forTenant($tenantId)->where('is_active', true)->orderBy('name')->get();
        $jobs = Job::where('tenant_id', $tenantId)->whereIn('status', Job::OPEN)
            ->whereIn('asset_id', $assets->pluck('id'))->get()->groupBy('asset_id');

        return response()->json(['data' => $assets->map(fn ($a) => $a->present() + [
            'open_jobs' => ($jobs[$a->id] ?? collect())->map(fn ($j) => ['id' => $j->uuid, 'status' => $j->status, 'title' => $j->title])->values(),
        ])->values()]);
    }

    public function history(Request $request, int $id)
    {
        $asset = Asset::forTenant($this->tenantId($request))->find($id);

        if (!$asset) {
            return $this->notFound('Activo');
        }

        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'limit' => 'nullable|integer|min:1|max:5000']);

        $query = Position::where('asset_id', $asset->id)
            ->where('recorded_at', '>=', $request->input('from', now()->subDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('recorded_at', '<=', $request->input('to')))
            ->orderBy('recorded_at')
            ->limit((int) $request->input('limit', 2000));

        return response()->json(['data' => $query->get()->map->present()->values()]);
    }

    /** Para quien ya tiene su propio GPS/tracker y empuja los puntos por API. */
    public function push(Request $request, int $id)
    {
        $asset = Asset::forTenant($this->tenantId($request))->where('is_active', true)->find($id);

        if (!$asset) {
            return $this->notFound('Activo');
        }

        $data = $request->validate([
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'recorded_at' => 'nullable|date',
            'speed'       => 'nullable|numeric|min:0',
            'heading'     => 'nullable|numeric|between:0,360',
            'accuracy'    => 'nullable|numeric|min:0',
            'altitude'    => 'nullable|numeric',
            'battery'     => 'nullable|integer|between:0,100',
        ]);

        try {
            $position = TrackingService::recordPosition($asset, $data);
        }
        catch (InvalidArgumentException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422);
        }

        return response()->json(['data' => $position->present()], 201);
    }
}
