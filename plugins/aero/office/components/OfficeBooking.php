<?php namespace Aero\Office\Components;

use Aero\Office\Classes\Availability;
use Aero\Office\Classes\BookingService;
use Aero\Office\Classes\OfficeException;
use Aero\Office\Models\Booking;
use Aero\Office\Models\Branch;
use Aero\Office\Models\Customer;
use Aero\Office\Models\OfficeSettings;
use Aero\Office\Models\Service;
use Aero\Office\Models\Worker;
use Aero\Sites\Models\Tenant;
use Carbon\Carbon;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Portal público de reservas del negocio del dominio. El tenant sale SIEMPRE
 * del host; el cliente nunca elige negocio. Sin cuenta: la cita se gestiona
 * con un token largo y aleatorio (manage_token) en /reservas/{token}.
 */
class OfficeBooking extends ComponentBase
{
    public ?Tenant $tenant = null;
    public ?OfficeSettings $settings = null;
    public array $catalog = [];
    public ?Booking $booking = null;
    public array $slots = [];
    public array $slotsMeta = [];
    public $user = null;

    public function componentDetails(): array
    {
        return ['name' => 'Reservas (portal público)', 'description' => 'Catálogo, calendario y formulario de reserva del negocio.'];
    }

    public function defineProperties(): array
    {
        return ['token' => ['title' => 'Token de la cita', 'default' => '{{ :token }}', 'type' => 'string']];
    }

    public function onRun()
    {
        if (!$this->boot()) {
            return $this->controller->run('404');
        }

        if ($token = trim((string) $this->property('token'))) {
            $this->booking = $this->findBooking($token);
            if (!$this->booking) {
                return $this->controller->run('404');
            }
            $this->page['isManage'] = true;
            $this->page['maxDate'] = now()->addDays((int) ($this->settings->max_advance_days ?: 60))->toDateString();
        } else {
            $this->catalog = $this->buildCatalog();
        }
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        $this->user = \Auth::getUser();
        if (!$this->tenant || !OfficeSettings::isPublic($this->tenant->id)) {
            return false;
        }
        $this->settings = OfficeSettings::forTenant($this->tenant->id);

        return true;
    }

    protected function findBooking(string $token): ?Booking
    {
        return strlen($token) >= 32 ? Booking::where('tenant_id', $this->tenant->id)->where('manage_token', $token)
            ->with(['service', 'branch', 'worker', 'customer'])->first() : null;
    }

    /** Solo lo público del tenant del dominio, con las relaciones para filtrar en el cliente. */
    protected function buildCatalog(): array
    {
        $tid = $this->tenant->id;
        $branches = Branch::where('tenant_id', $tid)->active()->with(['services:id', 'workers:id'])->orderBy('name')->get();
        $services = Service::where('tenant_id', $tid)->publicly()->with(['branches:id', 'workers:id'])->orderBy('category')->orderBy('name')->get();
        $workers = Worker::where('tenant_id', $tid)->publicly()->with(['branches:id', 'services:id'])->orderBy('name')->get();

        return [
            'branches' => $branches->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'address' => $b->address, 'phone' => $b->phone,
            ])->values()->all(),
            'services' => $services->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'category' => $s->category, 'description' => $s->description,
                'duration' => (int) $s->duration_minutes, 'price' => (float) $s->price, 'approval' => (bool) $s->requires_approval,
                'branch_ids' => $s->branches->pluck('id')->all(), 'worker_ids' => $s->workers->pluck('id')->all(),
            ])->values()->all(),
            'workers' => $workers->map(fn ($w) => [
                'id' => $w->id, 'name' => $w->name, 'title' => $w->title, 'bio' => $w->bio,
                'branch_ids' => $w->branches->pluck('id')->all(), 'service_ids' => $w->services->pluck('id')->all(),
            ])->values()->all(),
            'auto' => (bool) $this->settings->auto_assign_worker,
            'max_date' => now()->addDays((int) ($this->settings->max_advance_days ?: 60))->toDateString(),
        ];
    }

    /** AJAX: días del mes con al menos un horario libre (para pintar el calendario desplegable). */
    public function onDays()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('Reservas no disponibles.');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', (string) post('month'))) {
            throw new \ApplicationException('Mes no válido.');
        }

        [$service, $branch, $worker, $ignore] = $this->resolveSelection();

        $first = Carbon::createFromFormat('Y-m-d', post('month') . '-01')->startOfDay();
        $from = $first->lt(today()) ? today() : $first;
        $last = $first->copy()->endOfMonth()->startOfDay();
        $max = now()->addDays((int) ($this->settings->max_advance_days ?: 60))->startOfDay();
        if ($last->gt($max)) {
            $last = $max;
        }

        $days = [];
        for ($d = $from->copy(); $d->lte($last); $d->addDay()) {
            if (Availability::slots($service, $branch, $worker, $d, true, $ignore)) {
                $days[] = $d->toDateString();
            }
        }

        return ['days' => $days];
    }

    /** Servicio/sucursal/profesional pedidos (reserva nueva) o los de la cita (token). */
    protected function resolveSelection(): array
    {
        $tid = $this->tenant->id;
        if ($token = trim((string) post('token'))) {
            $b = $this->findBooking($token);
            if (!$b || !$b->service || !$b->branch) {
                throw new \ApplicationException('Cita no encontrada.');
            }

            return [$b->service, $b->branch, null, $b->id];
        }

        $service = Service::where('tenant_id', $tid)->publicly()->find((int) post('service_id'));
        $branch = Branch::where('tenant_id', $tid)->active()->find((int) post('branch_id'));
        $worker = post('worker_id') ? Worker::where('tenant_id', $tid)->publicly()->find((int) post('worker_id')) : null;
        if (post('worker_id') && !$worker) {
            throw new \ApplicationException('Profesional no disponible.');
        }
        if (!$service || !$branch) {
            throw new \ApplicationException('Elige sucursal y servicio.');
        }

        return [$service, $branch, $worker, null];
    }

    /** AJAX: horarios libres de un día (reserva nueva, o reprogramación si viene token). */
    public function onSlots()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('Reservas no disponibles.');
        }

        try {
            $day = Carbon::parse((string) post('date'))->startOfDay();
        } catch (\Throwable $e) {
            throw new \ApplicationException('Elige una fecha válida.');
        }
        if ($day->lt(today())) {
            $this->slots = [];

            return $this->renderSlots(null);
        }

        [$service, $branch, $worker, $ignore] = $this->resolveSelection();
        $token = trim((string) post('token'));

        $this->slots = array_map(fn ($s) => ['time' => $s['time'], 'starts_at' => $s['starts_at']->format('Y-m-d H:i'), 'n' => count($s['worker_ids'])],
            Availability::slots($service, $branch, $worker, $day, true, $ignore));
        $this->slotsMeta = ['date' => $day->translatedFormat('l d \d\e F'), 'token' => $token ?? null];

        return $this->renderSlots($day);
    }

    protected function renderSlots($day): array
    {
        $this->page['slots'] = $this->slots;
        $this->page['slotsMeta'] = $this->slotsMeta;

        return ['#office-slots' => $this->renderPartial('@slots')];
    }

    /** AJAX: crea la reserva. */
    public function onBook()
    {
        if (!$this->boot()) {
            throw new \ApplicationException('Reservas no disponibles.');
        }
        if (post('website')) { // honeypot
            throw new \ApplicationException('No se pudo completar la reserva.');
        }

        $key = 'office-book:' . $this->tenant->id . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new \ApplicationException('Demasiados intentos. Prueba de nuevo en unos minutos.');
        }
        RateLimiter::hit($key, 3600);

        $name = trim((string) post('name'));
        $phone = trim((string) post('phone'));
        $email = trim((string) post('email'));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new \ApplicationException('Escribe tu nombre.');
        }
        // El correo es obligatorio: con él se crea la cuenta del cliente para que vea sus reservas.
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \ApplicationException('Escribe un correo válido: con él crearemos tu cuenta para ver tus reservas.');
        }

        try {
            $customer = Customer::findMatch($this->tenant->id, $email ?: null, $phone ?: null)
                ?: Customer::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'phone' => $phone ?: null, 'email' => $email ?: null]);

            // Cliente con sesión: se vincula su usuario (solo si ese registro aún no tiene otro).
            if ($this->user && !$customer->user_id
                && !Customer::where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->exists()) {
                $customer->user_id = $this->user->id;
                $customer->save();
            }

            $booking = app(BookingService::class)->create($customer, [
                'branch_id'      => (int) post('branch_id'),
                'service_id'     => (int) post('service_id'),
                'worker_id'      => post('worker_id') ? (int) post('worker_id') : null,
                'starts_at'      => (string) post('starts_at'),
                'customer_notes' => mb_substr(trim((string) post('notes')), 0, 1000) ?: null,
            ], 'public');
        } catch (OfficeException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        // Anónimo: se vuelve usuario (rainlab:user) del negocio para revisar sus reservas en /cuenta.
        $message = null;
        if (!$this->user) {
            try {
                $users = app(\Aero\Office\Classes\CustomerUsers::class);
                $users->ensureUser($customer, request()->getSchemeAndHttpHost() . '/cuenta/restablecer');
                $message = $users->created
                    ? 'Creamos tu cuenta. Te enviamos un correo para que definas tu contraseña y veas todas tus reservas en «Mi cuenta».'
                    : 'Ya tienes una cuenta con este correo: ingresa a «Mi cuenta» para ver tus reservas.';
            } catch (\Throwable $e) {
                \Log::warning('[office] No se pudo crear la cuenta del cliente: ' . $e->getMessage());
            }
        }
        if ($message) {
            \Flash::success($message);
        }

        return \Redirect::to('/reservas/' . $booking->manage_token);
    }

    public function onCancelBooking()
    {
        return $this->withBooking(function (Booking $b) {
            app(BookingService::class)->cancel($b, 'Cancelada por el cliente', 'customer');
            \Flash::success('Tu cita fue cancelada.');
        });
    }

    public function onReschedule()
    {
        return $this->withBooking(function (Booking $b) {
            app(BookingService::class)->reschedule($b, (string) post('starts_at'), null, 'customer');
            \Flash::success('Tu cita fue reprogramada.');
        });
    }

    protected function withBooking(callable $fn)
    {
        if (!$this->boot()) {
            throw new \ApplicationException('Reservas no disponibles.');
        }
        $b = $this->findBooking(trim((string) post('token')));
        if (!$b) {
            throw new \ApplicationException('Cita no encontrada.');
        }

        try {
            $fn($b);
        } catch (OfficeException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return \Redirect::to('/reservas/' . $b->manage_token);
    }
}
