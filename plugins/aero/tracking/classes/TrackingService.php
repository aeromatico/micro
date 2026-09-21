<?php namespace Aero\Tracking\Classes;

use Aero\Tracking\Models\Asset;
use Aero\Tracking\Models\Job;
use Aero\Tracking\Models\Position;
use Aero\Tracking\Models\Stop;
use Carbon\Carbon;
use Event;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Camino único de escritura: lo usan la API, el panel y los plugins que se
 * integren después (shop, taxis...). Dispara eventos de dominio
 * `aero.tracking.*` para que otros reaccionen sin acoplarse.
 */
class TrackingService
{
    /**
     * $data: title, reference, external_type, external_id, notes, meta,
     * scheduled_at, asset_id, stops[] {type,name,address,lat,lng,...}.
     */
    public static function createJob(?int $tenantId, array $data, ?int $apiKeyId = null): Job
    {
        $assetId = $data['asset_id'] ?? null;

        if ($assetId && !Asset::where('id', $assetId)->where('tenant_id', $tenantId)->exists()) {
            throw new InvalidArgumentException('El activo no existe o no pertenece al tenant.');
        }

        $job = DB::transaction(function () use ($tenantId, $data, $apiKeyId, $assetId) {
            $job = new Job([
                'tenant_id'     => $tenantId,
                'asset_id'      => $assetId,
                'reference'     => $data['reference'] ?? null,
                'external_type' => $data['external_type'] ?? null,
                'external_id'   => isset($data['external_id']) ? (string) $data['external_id'] : null,
                'title'         => $data['title'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'meta'          => $data['meta'] ?? null,
                'scheduled_at'  => $data['scheduled_at'] ?? null,
                'status'        => $assetId ? 'assigned' : 'pending',
            ]);
            $job->api_key_id = $apiKeyId;
            $job->save();

            foreach (array_values($data['stops'] ?? []) as $i => $stop) {
                $job->stops()->create(array_merge(
                    array_intersect_key($stop, array_flip([
                        'type', 'name', 'address', 'lat', 'lng', 'contact_name',
                        'contact_phone', 'window_start', 'window_end', 'service_minutes', 'notes',
                    ])),
                    ['tenant_id' => $tenantId, 'sequence' => $i + 1]
                ));
            }

            return $job->load('stops');
        });

        Event::fire('aero.tracking.job.created', [$job]);

        return $job;
    }

    public static function assign(Job $job, ?Asset $asset): Job
    {
        if ($asset && $asset->tenant_id !== $job->tenant_id) {
            throw new InvalidArgumentException('El activo no pertenece al mismo tenant.');
        }

        if (!in_array($job->status, Job::OPEN, true)) {
            throw new InvalidArgumentException('El trabajo ya está cerrado.');
        }

        $job->asset_id = $asset?->id;
        $job->status = $asset ? ($job->status === 'in_progress' ? 'in_progress' : 'assigned') : 'pending';
        $job->save();

        Event::fire('aero.tracking.job.assigned', [$job, $asset]);

        return $job;
    }

    public static function setStatus(Job $job, string $status): Job
    {
        if (!isset(Job::STATUSES[$status])) {
            throw new InvalidArgumentException('Estado inválido.');
        }

        if ($job->status === $status) {
            return $job;
        }

        if (!in_array($job->status, Job::OPEN, true)) {
            throw new InvalidArgumentException('El trabajo ya está cerrado y no puede cambiar de estado.');
        }

        if (in_array($status, ['assigned', 'in_progress'], true) && !$job->asset_id) {
            throw new InvalidArgumentException('Asigna un activo antes de iniciar el trabajo.');
        }

        $job->status = $status;
        $job->started_at ??= $status === 'in_progress' ? now() : null;
        $job->completed_at = in_array($status, ['completed', 'cancelled', 'failed'], true) ? now() : null;
        $job->save();

        Event::fire('aero.tracking.job.status_changed', [$job]);

        return $job;
    }

    public static function setStopStatus(Stop $stop, string $status, ?array $proof = null): Stop
    {
        if (!in_array($status, Stop::STATUSES, true)) {
            throw new InvalidArgumentException('Estado de parada inválido.');
        }

        $stop->status = $status;

        if ($status === 'arrived') {
            $stop->arrived_at ??= now();
        }
        if (in_array($status, ['done', 'skipped', 'failed'], true)) {
            $stop->arrived_at ??= now();
            $stop->completed_at = now();
        }
        if ($proof !== null) {
            $stop->proof = $proof;
        }
        $stop->save();

        $event = $status === 'arrived' ? 'arrived' : (in_array($status, ['done', 'skipped', 'failed'], true) ? 'completed' : null);
        if ($event) {
            Event::fire("aero.tracking.stop.{$event}", [$stop]);
        }

        // Todas las paradas cerradas y el trabajo en curso: se completa solo.
        $job = $stop->job;
        if ($job && $job->status === 'in_progress' && !$job->stops()->whereIn('status', ['pending', 'arrived'])->exists()) {
            self::setStatus($job, 'completed');
        }

        return $stop;
    }

    /**
     * Registra un punto. Los puntos viejos (OwnTracks reenvía lo que quedó en
     * cola sin señal) van al historial pero no pisan la última posición.
     *
     * $p: lat, lng, recorded_at (Carbon|int|string), speed, heading,
     * accuracy, altitude, battery.
     */
    public static function recordPosition(Asset $asset, array $p): Position
    {
        $lat = (float) ($p['lat'] ?? 999);
        $lng = (float) ($p['lng'] ?? 999);

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0.0 && $lng == 0.0)) {
            throw new InvalidArgumentException('Coordenadas inválidas.');
        }

        $at = $p['recorded_at'] ?? now();
        $at = $at instanceof Carbon ? $at : (is_numeric($at) ? Carbon::createFromTimestamp((int) $at) : Carbon::parse($at));

        // Un reloj del dispositivo en el futuro no debe fijar la posición "actual".
        if ($at->gt(now()->addMinutes(2))) {
            $at = now();
        }

        $position = Position::create([
            'asset_id'    => $asset->id,
            'tenant_id'   => $asset->tenant_id,
            'lat'         => $lat,
            'lng'         => $lng,
            'speed'       => $p['speed'] ?? null,
            'heading'     => $p['heading'] ?? null,
            'accuracy'    => $p['accuracy'] ?? null,
            'altitude'    => $p['altitude'] ?? null,
            'battery'     => isset($p['battery']) ? max(0, min(100, (int) $p['battery'])) : null,
            'recorded_at' => $at,
            'created_at'  => now(),
        ]);

        if (!$asset->last_seen_at || $at->gte($asset->last_seen_at)) {
            $asset->newQuery()->whereKey($asset->id)->update([
                'last_lat'     => $lat,
                'last_lng'     => $lng,
                'last_speed'   => $position->speed,
                'last_heading' => $position->heading,
                'last_battery' => $position->battery,
                'last_seen_at' => $at,
            ]);
        }

        Event::fire('aero.tracking.asset.moved', [$asset, $position]);

        return $position;
    }
}
