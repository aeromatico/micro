<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Branch;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Post;
use Aero\Church\Models\Sermon;

/** Datos de la portada de UNA iglesia: solo lo que el visitante (o miembro) puede ver y las secciones activas. */
class HomeContent
{
    /** @return array|null null si la iglesia no tiene el sitio público activo */
    public static function forTenant(?int $tenantId, bool $isMember = false): ?array
    {
        $s = $tenantId ? ChurchSettings::where('tenant_id', $tenantId)->first() : null;
        if (!$s || !$s->site_enabled) {
            return null;
        }

        $events = ChurchEvent::forTenant($tenantId)->where('is_public', true)->upcoming()->orderBy('starts_at')->limit(4)->get()
            ->map(function ($e) {
                $d = $e->starts_local->locale('es');

                return [
                    'url'   => '/eventos/' . $e->id,
                    'title' => $e->title,
                    'place' => $e->location,
                    'day'   => $d->format('d'),
                    'month' => rtrim($d->isoFormat('MMM'), '.'),
                    'when'  => ucfirst($d->isoFormat('dddd D [de] MMMM')),
                    'time'  => $d->format('H:i'),
                    'open'  => $e->isOpenForRegistration(),
                    'full'  => $e->is_full,
                ];
            })->all();

        $date = fn ($d) => $d ? $d->copy()->locale('es')->isoFormat('D [de] MMMM') : null;

        $sermons = Sermon::forTenant($tenantId)->shown($isMember)->orderByDesc('preached_on')->orderByDesc('id')->limit(3)->get()
            ->map(fn ($x) => [
                'url' => '/predicaciones/' . $x->slug, 'title' => $x->title, 'preacher' => $x->preacher, 'summary' => $x->summary,
                'date' => $date($x->preached_on), 'media' => (bool) ($x->media_url || $x->audio_url), 'embed' => $x->embed_src,
            ])->all();

        $posts = Post::forTenant($tenantId)->shown($isMember)->orderByDesc('is_pinned')->orderByDesc('published_at')->limit(3)->get()
            ->map(fn ($p) => ['url' => '/noticias/' . $p->slug, 'title' => $p->title, 'body' => $p->body, 'date' => $date($p->published_at)])->all();

        $branches = Branch::forTenant($tenantId)->where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get()
            ->map(function ($b) {
                $where = trim(implode(', ', array_filter([$b->address, $b->city])));

                return [
                    'name' => $b->name, 'address' => $where, 'main' => (bool) $b->is_main,
                    'map' => $b->address ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($where) : null,
                ];
            })->all();

        // Los eventos son ocasiones especiales: si hay uno próximo, va destacado; si no, la próxima reunión.
        $event = $events[0] ?? null;
        $meeting = $event ? null : WeeklySchedule::next($tenantId);
        $next = $event ? [
            'label' => 'Evento especial', 'title' => $event['title'], 'day' => $event['day'], 'month' => $event['month'],
            'when' => $event['when'] . ' · ' . $event['time'], 'place' => $event['place'], 'url' => $event['url'],
            'action' => !$event['open'] ? 'Ver detalles' : ($event['full'] ? 'Anotarme en lista de espera' : 'Quiero asistir'),
        ] : ($meeting ? [
            'label' => $meeting['now'] ? 'Reunión en curso' : 'Próxima reunión', 'title' => $meeting['title'],
            'day' => $meeting['date']->format('d'), 'month' => rtrim($meeting['date']->copy()->locale('es')->isoFormat('MMM'), '.'),
            'when' => $meeting['when'] . ' · ' . $meeting['time'], 'place' => $meeting['place'], 'url' => '/horarios', 'action' => 'Ver todos los horarios',
        ] : null);

        return [
            'next'         => $next,
            'schedule'     => WeeklySchedule::days($tenantId),
            'welcome'      => (bool) $s->welcome_enabled,
            'prayer'       => (bool) $s->prayer_enabled,
            'portal'       => (bool) $s->portal_enabled,
            'live'         => $s->live_url ? ['title' => $s->live_title, 'schedule' => $s->live_schedule] : null,
            'events'       => $events,
            'sermons'      => $sermons,
            'posts'        => $posts,
            'branches'     => $branches,
        ];
    }
}
