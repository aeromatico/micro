<?php namespace Aero\Tracking\Http\Controllers\Api;

use Aero\Tracking\Classes\TrackingService;
use Aero\Tracking\Models\Asset;
use Aero\Tracking\Models\Job;
use Aero\Tracking\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class JobsController extends ApiController
{
    protected function find(Request $request, string $uuid): ?Job
    {
        return Job::with('stops')->where('tenant_id', $this->tenantId($request))->where('uuid', $uuid)->first();
    }

    public function index(Request $request)
    {
        $query = Job::with('stops')->where('tenant_id', $this->tenantId($request))->orderByDesc('id');

        foreach (['status', 'asset_id', 'reference', 'external_type', 'external_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->boolean('open')) {
            $query->whereIn('status', Job::OPEN);
        }

        return $this->paginated($query->paginate(min((int) $request->input('per_page', 50), 200)), fn ($j) => $j->present());
    }

    public function show(Request $request, string $uuid)
    {
        $job = $this->find($request, $uuid);

        return $job ? response()->json(['data' => $job->present()]) : $this->notFound('Trabajo');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'           => 'nullable|string|max:191',
            'reference'       => 'nullable|string|max:191',
            'external_type'   => 'nullable|string|max:191',
            'external_id'     => 'nullable|string|max:191',
            'notes'           => 'nullable|string|max:5000',
            'meta'            => 'nullable|array',
            'scheduled_at'    => 'nullable|date',
            'asset_id'        => 'nullable|integer',
            'stops'           => 'required|array|min:1|max:100',
            'stops.*.type'    => ['nullable', Rule::in(array_keys(Stop::TYPES))],
            'stops.*.name'    => 'nullable|string|max:191',
            'stops.*.address' => 'nullable|string|max:255',
            'stops.*.lat'     => 'nullable|numeric|between:-90,90',
            'stops.*.lng'     => 'nullable|numeric|between:-180,180',
            'stops.*.contact_name'    => 'nullable|string|max:191',
            'stops.*.contact_phone'   => 'nullable|string|max:30',
            'stops.*.window_start'    => 'nullable|date',
            'stops.*.window_end'      => 'nullable|date',
            'stops.*.service_minutes' => 'nullable|integer|min:0|max:1440',
            'stops.*.notes'           => 'nullable|string|max:2000',
        ]);

        try {
            $job = TrackingService::createJob($this->tenantId($request), $data, $this->apiKeyId($request));
        }
        catch (InvalidArgumentException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422);
        }

        return response()->json(['data' => $job->present()], 201);
    }

    public function assign(Request $request, string $uuid)
    {
        $job = $this->find($request, $uuid);

        if (!$job) {
            return $this->notFound('Trabajo');
        }

        $data = $request->validate(['asset_id' => 'present|nullable|integer']);
        $asset = $data['asset_id'] ? Asset::where('tenant_id', $job->tenant_id)->find($data['asset_id']) : null;

        if ($data['asset_id'] && !$asset) {
            return $this->notFound('Activo');
        }

        try {
            return response()->json(['data' => TrackingService::assign($job, $asset)->present()]);
        }
        catch (InvalidArgumentException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422);
        }
    }

    public function status(Request $request, string $uuid)
    {
        $job = $this->find($request, $uuid);

        if (!$job) {
            return $this->notFound('Trabajo');
        }

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Job::STATUSES))]]);

        try {
            return response()->json(['data' => TrackingService::setStatus($job, $data['status'])->present()]);
        }
        catch (InvalidArgumentException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422);
        }
    }

    public function stopStatus(Request $request, string $uuid, int $stopId)
    {
        $job = $this->find($request, $uuid);
        $stop = $job?->stops->firstWhere('id', $stopId);

        if (!$stop) {
            return $this->notFound('Parada');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(Stop::STATUSES)],
            'proof'  => 'nullable|array',
        ]);

        TrackingService::setStopStatus($stop, $data['status'], $data['proof'] ?? null);

        return response()->json(['data' => $job->fresh('stops')->present()]);
    }
}
