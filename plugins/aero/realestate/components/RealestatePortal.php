<?php namespace Aero\Realestate\Components;

use Aero\Realestate\Classes\Contact;
use Aero\Realestate\Classes\PortalAvailability;
use Aero\Realestate\Classes\PortalSearch;
use Aero\Realestate\Classes\PortalService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Favorite;
use Aero\Realestate\Models\Feature;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\RealestateSettings;
use Aero\Realestate\Models\SavedSearch;
use Aero\Sites\Models\Tenant;
use Carbon\Carbon;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Portal público de la inmobiliaria del dominio. El tenant sale SIEMPRE del host; el visitante nunca elige
 * inmobiliaria. Solo se muestra lo publicado y nada del propietario ni de notas internas.
 * Modos (propiedad `mode` de la página): list, detail, favorites, compare, alert.
 */
class RealestatePortal extends ComponentBase
{
    public const PER_PAGE = 12;
    public const COMPARE_MAX = 3;

    public ?Tenant $tenant = null;
    public ?RealestateSettings $settings = null;
    public $user = null;
    public string $mode = 'list';
    public int $compareMax = self::COMPARE_MAX;

    public array $filters = [];
    public array $result = [];
    public $featured = [];
    public $recommended = [];
    public array $facets = [];
    public array $markers = [];

    public ?Property $property = null;
    public $similar = [];
    public $items = [];
    public ?SavedSearch $search = null;

    public function componentDetails(): array
    {
        return ['name' => 'Inmobiliaria (portal público)', 'description' => 'Buscador, ficha, favoritos, comparador y alertas de la inmobiliaria.'];
    }

    public function defineProperties(): array
    {
        return [
            'mode'  => ['title' => 'Modo', 'type' => 'dropdown', 'default' => 'list', 'options' => ['list' => 'Buscador', 'detail' => 'Ficha', 'favorites' => 'Favoritos', 'compare' => 'Comparador', 'alert' => 'Baja de alerta']],
            'slug'  => ['title' => 'Slug', 'type' => 'string', 'default' => '{{ :slug }}'],
            'token' => ['title' => 'Token', 'type' => 'string', 'default' => '{{ :token }}'],
        ];
    }

    public function onRun()
    {
        if (!$this->boot()) {
            return $this->controller->run('404');
        }

        $this->mode = (string) $this->property('mode');
        $this->page['rePortal'] = $this;

        return match ($this->mode) {
            'detail'    => $this->runDetail(),
            'favorites' => $this->runCards($this->favoriteIds()),
            'compare'   => $this->runCompare(),
            'alert'     => $this->runAlert(),
            default     => $this->runList(),
        };
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        $this->user = \Auth::getUser();
        if (!$this->tenant || !RealestateSettings::isPublic($this->tenant->id)) {
            return false;
        }
        $this->settings = RealestateSettings::forTenant($this->tenant->id);

        return true;
    }

    // ───────────────────────── Buscador ─────────────────────────

    protected function runList()
    {
        $this->filters = PortalSearch::sanitize((array) request()->query());
        $page = max(1, (int) request()->query('page', 1));
        $p = PortalSearch::query($this->tenant->id, $this->filters)->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $this->result = ['items' => $p->items(), 'total' => $p->total(), 'page' => $p->currentPage(), 'pages' => $p->lastPage()];
        $this->markers = collect($p->items())->filter(fn ($x) => $x->lat && $x->lng)->map(fn ($x) => $this->marker($x))->values()->all();
        $this->facets = $this->buildFacets();

        if (!$this->filters) {
            $this->featured = PortalSearch::query($this->tenant->id, [])->where('is_featured', true)->limit(6)->get();
            $this->recommended = $this->recommendations();
        }
        $this->page['rePortal'] = $this;
    }

    /** Opciones de los filtros salidas de lo que realmente hay publicado (ciudades, zonas, tipos, características). */
    protected function buildFacets(): array
    {
        $base = Property::where('tenant_id', $this->tenant->id)->published()->whereHas('offers', fn ($o) => $o->where('status', 'available'));

        return Cache::remember('re-facets:' . $this->tenant->id, 300, fn () => [
            'departments' => (clone $base)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department')->all(),
            'cities'      => (clone $base)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->all(),
            'zones'       => (clone $base)->whereNotNull('zone')->distinct()->orderBy('zone')->pluck('zone')->all(),
            'features'    => Feature::where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $this->tenant->id))->orderBy('name')->get(['id', 'name'])->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all(),
        ]);
    }

    /** Recomendadas a un usuario con sesión según los intereses y el presupuesto de su ficha de cliente. */
    protected function recommendations()
    {
        if (!$this->user) {
            return [];
        }
        $client = Client::where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->first();
        if (!$client) {
            return [];
        }

        $f = ['modality' => ((array) $client->interests)[0] ?? null, 'currency' => $client->currency];
        $client->budget_max && $f[($f['modality'] ?? '') === 'anticretico' ? 'capital_max' : 'price_max'] = (float) $client->budget_max;

        return PortalSearch::query($this->tenant->id, PortalSearch::sanitize($f))->limit(4)->get();
    }

    public function marker(Property $p): array
    {
        return [
            'id' => $p->id, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng, 'title' => $p->title,
            'price' => $p->offers->first()?->label, 'url' => '/inmueble/' . $p->slug,
            'img' => $p->photos->first()?->getThumb(300, 200, ['mode' => 'crop']),
        ];
    }

    /** Enlace del buscador conservando los filtros (paginación, orden). */
    public function queryUrl(array $override = []): string
    {
        $q = array_merge($this->filters, $override);
        foreach ($q as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                unset($q[$k]);
            }
        }

        return '/inmuebles' . ($q ? '?' . http_build_query($q) : '');
    }

    // ───────────────────────── Ficha ─────────────────────────

    protected function runDetail()
    {
        $slug = (string) $this->property('slug');
        $p = Property::where('tenant_id', $this->tenant->id)->published()->where('slug', $slug)
            ->with(['offers' => fn ($o) => $o->whereIn('status', ['available', 'reserved'])->orderBy('modality'), 'photos', 'plans', 'features', 'agent.photo'])->first();
        if (!$p) {
            return $this->controller->run('404');
        }
        $this->property = $p;

        // Una visita por persona y hora: el contador no se infla recargando.
        if (Cache::add('re-view:' . $p->id . ':' . request()->ip(), 1, 3600)) {
            Property::whereKey($p->id)->increment('views_count');
        }

        $this->similar = $this->similarTo($p);
        $this->page['rePortal'] = $this;
        $this->page->title = $p->title;
        $this->page['maxDate'] = now()->addDays((int) ($this->settings->visit_advance_days ?: 30))->toDateString();
    }

    protected function similarTo(Property $p)
    {
        $q = PortalSearch::query($this->tenant->id, [])->where('id', '!=', $p->id)->where('type', $p->type);
        $p->city && $q->where('city', $p->city);
        $found = (clone $q)->limit(4)->get();

        if ($found->count() < 4) { // sin suficientes en la ciudad: mismo tipo en cualquier lugar
            $found = $found->concat(PortalSearch::query($this->tenant->id, [])->where('type', $p->type)->whereNotIn('id', $found->pluck('id')->push($p->id))->limit(4 - $found->count())->get());
        }
        return $found;
    }

    /** Contacto del agente responsable; si no tiene, el de la inmobiliaria. */
    public function contact(Property $p): array
    {
        $a = $p->agent;
        $phone = $a?->whatsapp ?: $a?->phone ?: $this->settings->whatsapp ?: $this->settings->phone;
        $msg = 'Hola, me interesa «' . $p->title . '» (' . $p->code . ') que vi en ' . request()->getHost() . '.';

        return [
            'name'     => $a?->name ?: ($this->settings->business_name ?: $this->tenant->name),
            'photo'    => $a?->photo?->getThumb(120, 120, ['mode' => 'crop']),
            'phone'    => $phone,
            'whatsapp' => Contact::whatsappUrl($phone, $msg),
        ];
    }

    /** URL de incrustación de un video de YouTube o Vimeo; null si el enlace no es de uno de los dos. */
    public function videoEmbed(Property $p): ?string
    {
        $u = (string) $p->video_url;
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $u, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $u, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }

    // ───────────────────────── Favoritos y comparador ─────────────────────────

    /** ids de la URL (?ids=1,2) más los guardados en la cuenta, si hay sesión. */
    protected function favoriteIds(): array
    {
        $ids = $this->idsFromRequest();
        if ($this->user) {
            $saved = Favorite::where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->pluck('property_id')->all();
            $ids = array_values(array_unique(array_merge($saved, $ids)));
        }

        return $ids;
    }

    protected function idsFromRequest(): array
    {
        return array_values(array_unique(array_slice(array_filter(array_map('intval', explode(',', (string) request()->query('ids', '')))), 0, 60)));
    }

    protected function cardsFor(array $ids)
    {
        if (!$ids) {
            return collect();
        }

        return Property::where('tenant_id', $this->tenant->id)->published()->whereIn('id', $ids)
            ->with(['offers' => fn ($o) => $o->whereIn('status', ['available', 'reserved'])->orderBy('modality'), 'photos', 'features', 'agent'])->get();
    }

    protected function runCards(array $ids)
    {
        $this->items = $this->cardsFor($ids);
        $this->markers = $this->items->filter(fn ($x) => $x->lat && $x->lng)->map(fn ($x) => $this->marker($x))->values()->all();
        $this->page['rePortal'] = $this;
    }

    protected function runCompare()
    {
        $this->items = $this->cardsFor(array_slice($this->idsFromRequest(), 0, self::COMPARE_MAX));
        $this->facets = ['features' => $this->items->flatMap(fn ($p) => $p->features->pluck('name'))->unique()->sort()->values()->all()];
        $this->page['rePortal'] = $this;
    }

    public function onFavorite()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('No disponible.');
        }
        $id = (int) post('id');
        if (!$this->user || !Property::where('tenant_id', $this->tenant->id)->published()->whereKey($id)->exists()) {
            return ['saved' => false]; // anónimo: queda solo en su navegador
        }

        $row = Favorite::where('user_id', $this->user->id)->where('property_id', $id)->first();
        $on = filter_var(post('on', true), FILTER_VALIDATE_BOOLEAN);
        if ($on && !$row) {
            Favorite::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'property_id' => $id, 'created_at' => now()]);
        } elseif (!$on && $row) {
            $row->delete();
        }

        return ['saved' => true];
    }

    /** Al iniciar sesión: sube a la cuenta los favoritos que el visitante tenía en el navegador. */
    public function onSyncFavorites()
    {
        if (!$this->boot() || !$this->user) {
            return ['ids' => []];
        }
        $ids = array_slice(array_filter(array_map('intval', (array) post('ids', []))), 0, 60);
        $valid = Property::where('tenant_id', $this->tenant->id)->published()->whereIn('id', $ids)->pluck('id')->all();
        foreach ($valid as $id) {
            Favorite::firstOrCreate(['user_id' => $this->user->id, 'property_id' => $id], ['tenant_id' => $this->tenant->id, 'created_at' => now()]);
        }

        return ['ids' => Favorite::where('user_id', $this->user->id)->where('tenant_id', $this->tenant->id)->pluck('property_id')->all()];
    }

    // ───────────────────────── Visitas y consultas ─────────────────────────

    public function onSlots()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('No disponible.');
        }
        $p = Property::where('tenant_id', $this->tenant->id)->published()->find((int) post('property_id'));
        if (!$p || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('date'))) {
            throw new \ApplicationException('Fecha no válida.');
        }
        $date = Carbon::createFromFormat('Y-m-d', post('date'));

        return ['#re-slots' => $this->renderPartial('@slots', ['slots' => PortalAvailability::slots($p, $date), 'date' => $date->toDateString()])];
    }

    public function onInquire()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('No disponible.');
        }
        if (post('website')) { // honeypot
            throw new \ApplicationException('No se pudo enviar el mensaje.');
        }
        $key = 'realestate-inquiry:' . $this->tenant->id . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw new \ApplicationException('Demasiados mensajes. Prueba de nuevo en unos minutos.');
        }
        RateLimiter::hit($key, 3600);

        $property = Property::where('tenant_id', $this->tenant->id)->published()->find((int) post('property_id'));
        if (!$property) {
            throw new \ApplicationException('La propiedad ya no está disponible.');
        }

        try {
            [$client, , $visit] = app(PortalService::class)->inquire($property, [
                'name' => post('name'), 'email' => post('email'), 'phone' => post('phone'), 'message' => post('message'),
                'offer_id' => post('offer_id'), 'visit_at' => post('visit_at') ?: null,
            ], $this->user);
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        // Anónimo con correo: se vuelve usuario del negocio para guardar favoritos y alertas (invitación para elegir contraseña).
        $account = null;
        if (!$this->user && $client->email) {
            try {
                $users = app(\Aero\Realestate\Classes\PersonUsers::class);
                $users->ensureUser($client, request()->getSchemeAndHttpHost() . '/cuenta/restablecer');
                $account = $users->created
                    ? 'Creamos tu cuenta: te enviamos un correo para que definas tu contraseña y guardes favoritos.'
                    : 'Ya tienes una cuenta con este correo: ingresa para ver tus favoritos.';
            } catch (\Throwable $e) {
                \Log::warning('[realestate] No se pudo crear la cuenta del cliente: ' . $e->getMessage());
            }
        }

        return ['#re-inquiry' => $this->renderPartial('@thanks', ['visit' => $visit, 'account' => $account, 'contact' => $this->contact($property)])];
    }

    // ───────────────────────── Alertas ─────────────────────────

    public function onSaveSearch()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('No disponible.');
        }
        if (post('website')) {
            throw new \ApplicationException('No se pudo guardar.');
        }
        $key = 'realestate-search:' . $this->tenant->id . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw new \ApplicationException('Demasiadas búsquedas guardadas. Prueba más tarde.');
        }
        RateLimiter::hit($key, 3600);

        parse_str((string) post('criteria'), $raw);
        $criteria = PortalSearch::sanitize($raw);
        unset($criteria['sort']);
        if (!$criteria) {
            throw new \ApplicationException('Elige al menos un filtro para guardar la búsqueda.');
        }

        $email = trim((string) ($this->user?->email ?: post('email')));
        $phone = preg_replace('/[^\d+]/', '', (string) post('phone'));
        if ($email === '' && $phone === '') {
            throw new \ApplicationException('Déjanos un correo o un teléfono para avisarte.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \ApplicationException('El correo no es válido.');
        }
        if (SavedSearch::where('tenant_id', $this->tenant->id)->where('is_active', true)->where(fn ($q) => $q->where('email', $email ?: '-')->orWhere('phone', $phone ?: '-'))->count() >= 10) {
            throw new \ApplicationException('Ya tienes 10 alertas activas.');
        }

        SavedSearch::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user?->id, 'name' => mb_substr(trim((string) post('name')) ?: PortalSearch::describe($criteria), 0, 120),
            'criteria' => $criteria, 'email' => $email ?: null, 'phone' => $phone ?: null,
        ]);

        return ['#re-alert-box' => '<p class="rounded-brand bg-green-100 p-4 text-sm text-green-800">Listo: te avisaremos cuando publiquemos propiedades que cumplan esta búsqueda.</p>'];
    }

    protected function runAlert()
    {
        $this->search = SavedSearch::where('tenant_id', $this->tenant->id)->where('token', (string) $this->property('token'))->first();
        if (!$this->search) {
            return $this->controller->run('404');
        }
        $this->page['rePortal'] = $this;
    }

    public function onUnsubscribe()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('No disponible.');
        }
        SavedSearch::where('tenant_id', $this->tenant->id)->where('token', (string) post('token'))->update(['is_active' => false]);

        return ['#re-unsub' => '<p class="rounded-brand bg-green-100 p-4 text-sm text-green-800">Listo, no recibirás más avisos de esta búsqueda.</p>'];
    }
}
