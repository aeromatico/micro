<?php namespace Aero\Church\Components;

use Aero\Church\Classes\EventService;
use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\PortalAccess;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Post;
use Aero\Church\Models\Resource;
use Aero\Church\Models\Series;
use Aero\Church\Models\Sermon;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sitio público de la iglesia del dominio. El tenant sale SIEMPRE del host; apagado hasta que la iglesia lo
 * activa. Lo «solo miembros» se oculta a visitantes. Modos: sermons, sermon, news, post, live, events, event, resources.
 */
class ChurchSite extends ComponentBase
{
    use \Aero\Church\Classes\FallsThrough;

    public const PER_PAGE = 12;

    public ?Tenant $tenant = null;
    public ?ChurchSettings $settings = null;
    public $user = null;
    public bool $isMember = false;
    public string $mode = 'sermons';

    public $items = null;
    public $item = null;
    public array $filters = [];
    public array $facets = [];
    public ?string $liveSrc = null;
    public array $resourcesByKind = [];
    public ?array $registered = null;
    public bool $membersOnly = false;

    public function componentDetails(): array
    {
        return ['name' => 'Iglesia (sitio público)', 'description' => 'Predicaciones, noticias, eventos, transmisión en vivo y recursos.'];
    }

    public function defineProperties(): array
    {
        return [
            'mode' => ['title' => 'Sección', 'type' => 'dropdown', 'default' => 'sermons',
                'options' => ['sermons' => 'Predicaciones', 'news' => 'Noticias', 'live' => 'En vivo', 'events' => 'Eventos', 'resources' => 'Recursos']],
            'slug' => ['title' => 'Slug/ID', 'type' => 'string', 'default' => '{{ :slug }}'],
        ];
    }

    public function onRun()
    {
        $this->mode = (string) $this->property('mode');
        if (!$this->boot()) {
            return $this->fallThrough();
        }

        $slug = (string) $this->property('slug');
        $found = match ($this->mode) {
            'news'      => $slug !== '' ? $this->runPost($slug) : $this->runNews(),
            'live'      => $this->runLive(),
            'events'    => $slug !== '' ? $this->runEvent($slug) : $this->runEvents(),
            'resources' => $this->runResources(),
            default     => $slug !== '' ? $this->runSermon($slug) : $this->runSermons(),
        };
        if ($found === false) {
            return $this->controller->run('404');
        }
        $this->page['churchSite'] = $this;
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        if (!$this->tenant) {
            return false;
        }
        $this->settings = ChurchSettings::where('tenant_id', $this->tenant->id)->first();
        if (!$this->settings || !$this->settings->site_enabled) {
            return false;
        }
        $this->user = \Auth::user();
        $this->isMember = PortalAccess::isMember($this->user, $this->tenant->id);

        return true;
    }

    public function churchName(): string
    {
        return (string) $this->tenant?->name;
    }

    public function portalEnabled(): bool
    {
        return (bool) $this->settings?->portal_enabled;
    }

    // ───────────── Predicaciones ─────────────

    protected function runSermons()
    {
        $q = Sermon::forTenant($this->tenant->id)->shown($this->isMember)->with('series');

        $this->filters = [
            'q' => trim((string) request()->query('q')), 'series' => (int) request()->query('series'),
            'preacher' => trim((string) request()->query('preacher')),
        ];
        if ($this->filters['q'] !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $this->filters['q']) . '%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('preacher', 'like', $term)->orWhere('topic', 'like', $term)->orWhere('summary', 'like', $term));
        }
        if ($this->filters['series']) {
            $q->where('series_id', $this->filters['series']);
        }
        if ($this->filters['preacher'] !== '') {
            $q->where('preacher', $this->filters['preacher']);
        }

        $page = max(1, (int) request()->query('page', 1));
        $this->items = $q->orderByDesc('preached_on')->orderByDesc('id')->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $base = Sermon::forTenant($this->tenant->id)->shown($this->isMember);
        $this->facets = [
            'series'    => Series::forTenant($this->tenant->id)->whereIn('id', (clone $base)->whereNotNull('series_id')->select('series_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'preachers' => (clone $base)->whereNotNull('preacher')->distinct()->orderBy('preacher')->pluck('preacher')->all(),
        ];
    }

    protected function runSermon(string $slug)
    {
        $s = Sermon::forTenant($this->tenant->id)->where('is_published', true)->where('slug', $slug)->first();
        if (!$s) {
            return false;
        }
        $this->membersOnly = $s->visibility === 'members' && !$this->isMember;
        $this->item = $s;
        if (!$this->membersOnly) {
            $this->items = $s->resources()->shown($this->isMember)->get();
        }
    }

    // ───────────── Noticias ─────────────

    protected function runNews()
    {
        $page = max(1, (int) request()->query('page', 1));
        $this->items = Post::forTenant($this->tenant->id)->shown($this->isMember)
            ->orderByDesc('is_pinned')->orderByDesc('published_at')->paginate(10, ['*'], 'page', $page);
    }

    protected function runPost(string $slug)
    {
        $p = Post::forTenant($this->tenant->id)->shown(true)->where('slug', $slug)->first();
        if (!$p) {
            return false;
        }
        $this->membersOnly = $p->visibility === 'members' && !$this->isMember;
        $this->item = $p;
    }

    // ───────────── En vivo ─────────────

    protected function runLive()
    {
        $this->liveSrc = \Aero\Church\Classes\Embed::src($this->settings->live_url);
        $this->items = ChurchEvent::forTenant($this->tenant->id)->where('is_public', true)->upcoming()->orderBy('starts_at')->limit(5)->get();
    }

    // ───────────── Eventos ─────────────

    protected function runEvents()
    {
        $this->items = ChurchEvent::forTenant($this->tenant->id)->where('is_public', true)->upcoming()->orderBy('starts_at')->get();
    }

    protected function runEvent(string $id)
    {
        $e = ChurchEvent::forTenant($this->tenant->id)->where('is_public', true)->where('status', 'scheduled')->find((int) $id);
        if (!$e) {
            return false;
        }
        $this->item = $e;
    }

    // ───────────── Recursos ─────────────

    protected function runResources()
    {
        $kinds = Resource::KINDS;
        $rows = Resource::forTenant($this->tenant->id)->shown($this->isMember)->orderBy('title')->get();
        foreach ($kinds as $k => $label) {
            $list = $rows->where('kind', $k)->values();
            if ($list->isNotEmpty()) {
                $this->resourcesByKind[$label] = $list;
            }
        }
    }

    // ───────────── Inscripción a un evento ─────────────

    public function onRegisterEvent()
    {
        $this->mode = 'events';
        if (!$this->boot()) {
            throw new \ApplicationException('No disponible.');
        }
        if (post('website')) {
            throw new \ApplicationException('No se pudo enviar.');
        }
        $key = 'church-event:' . $this->tenant->id . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw new \ApplicationException('Demasiados intentos. Prueba de nuevo en un rato.');
        }
        RateLimiter::hit($key, 3600);

        $event = ChurchEvent::forTenant($this->tenant->id)->where('is_public', true)->find((int) post('event_id'));
        if (!$event) {
            throw new \ApplicationException('El evento ya no está disponible.');
        }

        try {
            $svc = app(EventService::class);
            if ($member = PortalAccess::memberFor($this->user, $this->tenant->id)) {
                $reg = $svc->register($event, $member);
            } else {
                if (!post('consent_data')) {
                    throw new ChurchException('Necesitamos tu autorización para guardar tus datos.');
                }
                $reg = $svc->register($event, null, post('name'), post('phone'));
            }
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return ['#church-register' => $this->renderPartial('@registered', ['reg' => $reg, 'event' => $event])];
    }
}
