<?php namespace Aero\Tracking\Classes\Feeds;

use Aero\Tracking\Classes\TrackingService;
use Aero\Tracking\Jobs\PollFeed;
use Aero\Tracking\Models\Asset;
use Aero\Tracking\Models\Feed;
use Aero\Tracking\Models\FeedEvent;
use Aero\Tracking\Models\Stop;
use Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Camino único para seguir enlaces públicos de terceros. Cada feed es un Job
 * (+ Asset virtual con el repartidor) de Tracking, de modo que mapa, API y
 * eventos aero.tracking.* funcionan sin cambios. Eventos propios:
 * aero.tracking.feed.{started,phase_changed,eta_changed,finished,expired,failed}.
 *
 * El polling se encadena solo: cada PollFeed se re-encola con retraso. No
 * depende de cron (este proyecto solo tiene worker de colas).
 */
class FeedService
{
    /** Distancia mínima entre posiciones guardadas (m): evita ruido de GPS parado. */
    public const MIN_MOVE_METERS = 30;

    /** Un feed activo más de este tiempo se da por vencido. */
    public const MAX_HOURS = 12;

    public const MAX_ERRORS = 8;

    public static function add(?int $tenantId, string $url, ?string $label = null, ?int $userId = null): Feed
    {
        $found = FeedRegistry::detect($url);
        if (!$found) {
            throw new InvalidArgumentException('No reconozco ese enlace. Hoy se admite: ' . implode(', ', FeedRegistry::labels()) . '.');
        }

        [$driver, $match] = $found;

        $dup = Feed::where('tenant_id', $tenantId)->where('provider', $driver->key())
            ->where('external_id', $match['external_id'])->where('status', 'active')->first();
        if ($dup) {
            throw new InvalidArgumentException('Ese enlace ya se está siguiendo.');
        }

        $feed = new Feed();
        $feed->forceFill([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'user_id' => $userId,
            'provider' => $driver->key(), 'url' => $match['url'], 'external_id' => $match['external_id'],
            'label' => $label ?: null, 'status' => 'active',
        ]);

        // Primera lectura ANTES de crear nada: si el enlace es inválido o venció no queda basura.
        try {
            $snap = $driver->poll($feed);
        } catch (FeedGoneException $e) {
            throw new InvalidArgumentException('El enlace no es válido o ya venció.');
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('No pude leer el enlace ahora mismo: ' . $e->getMessage());
        }

        if (in_array($snap['phase'], ['delivered', 'cancelled'], true)) {
            throw new InvalidArgumentException('Ese pedido ya terminó; solo se siguen pedidos en curso.');
        }

        DB::transaction(function () use ($feed, $snap, $driver, $tenantId) {
            $asset = Asset::create([
                'tenant_id' => $tenantId, 'name' => $feed->label ?: ($snap['title'] ?: $driver->label()),
                'type' => 'external', 'is_active' => true, 'meta' => ['feed' => $driver->key()],
            ]);

            $stops = [];
            if ($snap['origin']) {
                $stops[] = ['type' => 'pickup', 'name' => $snap['title'] ?: 'Origen', 'lat' => $snap['origin'][0], 'lng' => $snap['origin'][1]];
            }
            if ($snap['destination']) {
                $stops[] = ['type' => 'delivery', 'name' => 'Destino', 'lat' => $snap['destination'][0], 'lng' => $snap['destination'][1]];
            }

            $job = TrackingService::createJob($tenantId, [
                'title' => $feed->label ?: ($snap['title'] ?: $driver->label()),
                'reference' => $feed->external_id, 'external_type' => $driver->key(), 'external_id' => $feed->external_id,
                'asset_id' => $asset->id, 'meta' => ['feed_uuid' => $feed->uuid], 'stops' => $stops,
            ]);

            $feed->asset_id = $asset->id;
            $feed->job_id = $job->id;
            $feed->save();
        });

        self::event($feed, 'started', ['phase' => $snap['phase'], 'title' => $snap['title']]);
        Event::fire('aero.tracking.feed.started', [$feed]);

        self::apply($feed, $snap);

        return $feed;
    }

    /** Una vuelta de polling. Devuelve false si no hizo nada (bloqueado o inactivo). */
    public static function poll(Feed $feed): bool
    {
        $lock = Cache::lock("aero:tracking:feed:{$feed->id}", 60);
        if (!$feed->isActive() || !$lock->get()) {
            return false;
        }

        try {
            $feed->refresh();
            if (!$feed->isActive()) {
                return false;
            }

            if ($feed->created_at->lt(now()->subHours(self::MAX_HOURS))) {
                return self::close($feed, 'expired', 'El seguimiento superó el tiempo máximo.');
            }

            $driver = FeedRegistry::get($feed->provider);
            if (!$driver) {
                return self::close($feed, 'failed', 'El proveedor ya no está disponible.');
            }

            try {
                $snap = $driver->poll($feed);
            } catch (FeedGoneException $e) {
                return self::close($feed, 'expired', $e->getMessage());
            } catch (\Throwable $e) {
                $feed->error_count++;
                $feed->last_error = mb_substr($e->getMessage(), 0, 250);
                $feed->last_polled_at = now();

                if ($feed->error_count >= self::MAX_ERRORS) {
                    return self::close($feed, 'failed', $feed->last_error);
                }

                $feed->save();
                self::schedule($feed, min(300, 10 * (2 ** $feed->error_count)));

                return true;
            }

            $feed->error_count = 0;
            $feed->last_error = null;
            self::apply($feed, $snap);

            return true;
        } finally {
            $lock->release();
        }
    }

    /** Compara contra el último estado, emite eventos y refleja todo en Job/Asset. */
    protected static function apply(Feed $feed, array $s): void
    {
        $old = (array) $feed->state;
        $oldPhase = $feed->phase;

        if ($oldPhase !== $s['phase']) {
            if ($oldPhase !== null) {
                self::event($feed, 'phase_changed', ['from' => $oldPhase, 'to' => $s['phase']]);
                Event::fire('aero.tracking.feed.phase_changed', [$feed, $oldPhase, $s['phase']]);
            }
        }
        if (($old['eta_text'] ?? null) !== $s['eta_text'] && $s['eta_text'] !== null && $old) {
            self::event($feed, 'eta_changed', ['from' => $old['eta_text'] ?? null, 'to' => $s['eta_text']]);
            Event::fire('aero.tracking.feed.eta_changed', [$feed, $old['eta_text'] ?? null, $s['eta_text']]);
        }
        if (($old['delay_label'] ?? null) !== $s['delay_label'] && $s['delay_label'] !== null && $old) {
            self::event($feed, 'delay_changed', ['from' => $old['delay_label'] ?? null, 'to' => $s['delay_label']]);
        }
        if (($old['message'] ?? null) !== $s['message'] && $s['message'] !== null && $old) {
            self::event($feed, 'message_changed', ['text' => $s['message']]);
        }

        // Posición: solo si es nueva y se movió lo suficiente.
        $moved = false;
        if ($s['lat'] !== null && $s['lng'] !== null && $feed->asset) {
            $prevLat = $old['lat'] ?? null;
            $prevLng = $old['lng'] ?? null;
            $isNew = !isset($old['position_at']) || ($s['position_at'] && $s['position_at']->toIso8601String() !== $old['position_at']);
            $moved = $isNew && ($prevLat === null || self::meters($prevLat, $prevLng, $s['lat'], $s['lng']) >= self::MIN_MOVE_METERS);

            if ($moved) {
                try {
                    TrackingService::recordPosition($feed->asset, ['lat' => $s['lat'], 'lng' => $s['lng'], 'recorded_at' => $s['position_at'] ?? now()]);
                } catch (InvalidArgumentException) {
                    $moved = false;
                }
            }
        }

        $feed->phase = $s['phase'];
        $feed->eta_text = $s['eta_text'];
        $feed->delay_label = $s['delay_label'];
        $feed->message = $s['message'] ? mb_substr($s['message'], 0, 250) : null;
        $feed->poll_seconds = $s['interval'];
        $feed->last_polled_at = now();
        $feed->state = [
            'title' => $s['title'], 'eta_text' => $s['eta_text'], 'delay_label' => $s['delay_label'], 'message' => $s['message'],
            // Si no se guardó el punto por no haberse movido, se conserva el anterior como referencia.
            'lat' => $moved || !isset($old['lat']) ? $s['lat'] : $old['lat'],
            'lng' => $moved || !isset($old['lng']) ? $s['lng'] : $old['lng'],
            'position_at' => $moved || !isset($old['position_at']) ? $s['position_at']?->toIso8601String() : $old['position_at'],
        ];

        self::syncJob($feed, $oldPhase, $s['phase']);

        if (in_array($s['phase'], ['delivered', 'cancelled'], true)) {
            $feed->status = $s['phase'] === 'delivered' ? 'completed' : 'cancelled';
            $feed->finished_at = now();
            $feed->next_poll_at = null;
            $feed->save();
            $feed->asset?->update(['is_active' => false]);
            self::event($feed, 'finished', ['outcome' => $s['phase']]);
            Event::fire('aero.tracking.feed.finished', [$feed, $s['phase']]);

            return;
        }

        $feed->save();
        self::schedule($feed, $s['interval']);
    }

    protected static function syncJob(Feed $feed, ?string $from, string $to): void
    {
        $job = $feed->job;
        if (!$job || $from === $to) {
            return;
        }

        try {
            if (in_array($to, ['on_the_way', 'delivered'], true) && $job->status === 'assigned') {
                TrackingService::setStatus($job, 'in_progress');
                $job->stops()->where('type', 'pickup')->get()->each(fn (Stop $st) => TrackingService::setStopStatus($st, 'done'));
            }

            if ($to === 'delivered') {
                $job->refresh();
                $job->stops()->where('type', 'delivery')->get()->each(fn (Stop $st) => TrackingService::setStopStatus($st, 'done'));
                $job->refresh();
                if (in_array($job->status, \Aero\Tracking\Models\Job::OPEN, true)) {
                    TrackingService::setStatus($job, 'completed');
                }
            } elseif ($to === 'cancelled') {
                TrackingService::setStatus($job, 'cancelled');
            }
        } catch (InvalidArgumentException) {
            // El trabajo ya estaba cerrado por otra vía: el feed manda sobre sí mismo, no sobre el Job.
        }
    }

    /** Cierra el seguimiento por una causa distinta de entrega/cancelación. */
    public static function close(Feed $feed, string $status, ?string $reason = null): bool
    {
        $feed->status = $status;
        $feed->finished_at = now();
        $feed->next_poll_at = null;
        $feed->last_error = $reason ? mb_substr($reason, 0, 250) : $feed->last_error;
        $feed->save();
        $feed->asset?->update(['is_active' => false]);

        if ($feed->job && in_array($feed->job->status, \Aero\Tracking\Models\Job::OPEN, true) && $status !== 'stopped') {
            try {
                TrackingService::setStatus($feed->job, 'failed');
            } catch (InvalidArgumentException) {
            }
        }

        self::event($feed, $status, ['reason' => $reason]);
        if (in_array($status, ['expired', 'failed'], true)) {
            Event::fire("aero.tracking.feed.{$status}", [$feed, $reason]);
        }

        return true;
    }

    public static function stop(Feed $feed): void
    {
        if ($feed->isActive()) {
            self::close($feed, 'stopped', 'Detenido por el usuario.');
        }
    }

    /** Encola la siguiente vuelta. */
    public static function schedule(Feed $feed, int $seconds): void
    {
        $seconds = max(5, $seconds);
        $feed->newQuery()->whereKey($feed->id)->update(['next_poll_at' => now()->addSeconds($seconds)]);
        PollFeed::dispatch($feed->id)->delay(now()->addSeconds($seconds));
    }

    /** Red de seguridad: reengancha cadenas que murieron (worker caído, job perdido). */
    public static function sweep(): int
    {
        $n = 0;
        Feed::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('next_poll_at')->orWhere('next_poll_at', '<', now()->subSeconds(90)))
            ->each(function (Feed $feed) use (&$n) {
                self::schedule($feed, 5);
                $n++;
            });

        return $n;
    }

    public static function event(Feed $feed, string $type, array $data = []): void
    {
        FeedEvent::create([
            'feed_id' => $feed->id, 'tenant_id' => $feed->tenant_id, 'type' => $type,
            'data' => $data, 'occurred_at' => now(), 'created_at' => now(),
        ]);
    }

    protected static function meters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
