<?php namespace Aero\Gym\Components;

use Aero\Gym\Classes\BookingService;
use Aero\Gym\Classes\GymException;
use Aero\Gym\Models\Booking;
use Aero\Gym\Models\ClassSession;
use Aero\Gym\Models\GymSettings;
use Aero\Gym\Models\Member;
use Aero\Sites\Models\Tenant;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Cms\Classes\ComponentBase;

/**
 * Portal del socio en el micrositio del gimnasio: su membresía, el QR de
 * ingreso, las clases que puede reservar y su rutina. El socio es el usuario
 * de rainlab:user vinculado a un Member DEL TENANT DEL DOMINIO; cualquier otra
 * combinación no ve nada (falla cerrado).
 */
class GymPortal extends ComponentBase
{
    public ?Tenant $tenant = null;
    public $user = null;
    public ?Member $member = null;

    public bool $enabled = false;
    public array $summary = [];
    public string $qrSvg = '';
    public array $days = [];
    public array $mine = [];
    public ?array $routine = null;
    public string $accessMode = 'member_qr';
    public ?array $checkin = null;

    public function componentDetails(): array
    {
        return ['name' => 'Gimnasio (portal del socio)', 'description' => 'Membresía, QR, reservas y rutina del socio.'];
    }

    public function onRun()
    {
        if (!$this->boot()) {
            return $this->controller->run('404');
        }

        // El socio escaneó el QR del gimnasio: se resuelve una vez y se redirige a /gym limpio
        // (así recargar la página no vuelve a registrar un ingreso).
        if (($token = (string) request()->query('checkin')) !== '' && $this->member) {
            \Session::flash('gym_checkin', app(\Aero\Gym\Classes\AccessControl::class)->checkWithGymQr($this->member, $token));

            return \Redirect::to('/gym');
        }

        $this->load();
        $this->checkin = \Session::get('gym_checkin');
    }

    /** Handlers AJAX: resuelven tenant/usuario/socio por su cuenta. */
    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        $this->user = $this->user ?: \Auth::getUser();
        if (!$this->tenant) {
            return false;
        }

        $this->enabled = GymSettings::isEnabled($this->tenant->id) && self::siteHasGym($this->tenant->id);
        if (!$this->enabled) {
            return false;
        }

        $this->member = $this->user
            ? Member::where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->first()
            : null;

        return true;
    }

    /** El sitio muestra el portal si el gimnasio tiene al menos socios o planes. */
    public static function siteHasGym(int $tenantId): bool
    {
        return Member::where('tenant_id', $tenantId)->exists() || \Aero\Gym\Models\Plan::where('tenant_id', $tenantId)->exists();
    }

    protected function load(): void
    {
        $m = $this->member;
        if (!$m) {
            return;
        }

        $settings = GymSettings::forTenant($m->tenant_id);
        $grace = (int) ($settings->grace_days ?? 0);
        $current = $m->currentMembership($grace);
        $last = $current ?: $m->memberships()->whereIn('status', ['active', 'expired', 'pending'])->orderByDesc('ends_on')->first();

        $this->summary = [
            'name'      => $m->name,
            'first'     => explode(' ', trim($m->name))[0],
            'status'    => $m->status !== 'active' ? 'blocked' : ($current ? 'active' : ($last?->status === 'pending' ? 'pending' : 'expired')),
            'plan'      => $last?->plan?->name,
            'ends_on'   => $last?->ends_on?->translatedFormat('d \d\e F'),
            'days_left' => $current ? max(0, (int) today()->diffInDays($current->ends_on, false)) : null,
            'token'     => $m->qr_token,
            'classes_per_week' => $current?->plan?->classes_per_week,
        ];

        $this->accessMode = GymSettings::accessMode($m->tenant_id);
        $this->qrSvg = $this->makeQr((string) $m->qr_token);
        $this->loadAgenda($m);
        $this->loadRoutine($m);
    }

    protected function loadAgenda(Member $m): void
    {
        $bookings = Booking::where('member_id', $m->id)->whereIn('status', ['booked', 'waitlist'])->get()->keyBy('session_id');

        $sessions = ClassSession::with(['classType', 'instructor'])
            ->where('tenant_id', $m->tenant_id)->where('status', 'scheduled')
            ->where('starts_at', '>=', now())->where('starts_at', '<=', now()->addDays(7)->endOfDay())
            ->orderBy('starts_at')->get();

        $seated = Booking::whereIn('session_id', $sessions->pluck('id'))->whereIn('status', Booking::SEATED)
            ->selectRaw('session_id, count(*) n')->groupBy('session_id')->pluck('n', 'session_id');

        $days = [];
        foreach ($sessions as $s) {
            $b = $bookings->get($s->id);
            $taken = (int) ($seated[$s->id] ?? 0);
            $key = $s->starts_at->toDateString();
            $days[$key]['label'] = $s->starts_at->isToday() ? 'Hoy' : ($s->starts_at->isTomorrow() ? 'Mañana' : ucfirst($s->starts_at->translatedFormat('l d')));
            $days[$key]['items'][] = [
                'id'         => $s->id,
                'name'       => $s->classType?->name ?: 'Clase',
                'color'      => $s->classType?->color ?: null,
                'time'       => $s->starts_at->format('H:i'),
                'ends'       => $s->ends_at->format('H:i'),
                'instructor' => $s->instructor?->name,
                'room'       => $s->room,
                'free'       => max(0, $s->capacity - $taken),
                'mine'       => $b?->status,
            ];
        }
        $this->days = array_values($days);

        $this->mine = $sessions->filter(fn ($s) => $bookings->has($s->id))->map(fn ($s) => [
            'name' => $s->classType?->name ?: 'Clase',
            'when' => ucfirst($s->starts_at->translatedFormat('D d, H:i')),
            'status' => $bookings[$s->id]->status,
        ])->values()->all();
    }

    protected function loadRoutine(Member $m): void
    {
        $r = $m->routines()->where('is_active', true)->with('items')->orderByDesc('id')->first();
        if (!$r) {
            return;
        }
        $groups = [];
        foreach ($r->items as $i) {
            $groups[$i->day_label ?: 'Rutina'][] = [
                'exercise' => $i->exercise, 'sets' => $i->sets, 'reps' => $i->reps, 'rest' => $i->rest_seconds, 'notes' => $i->notes,
            ];
        }
        $this->routine = ['name' => $r->name, 'goal' => $r->goal, 'notes' => $r->notes, 'groups' => $groups];
    }

    protected function makeQr(string $text): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 0), new SvgImageBackEnd()));

        return $writer->writeString($text);
    }

    public function onBook()
    {
        return $this->act(function (Member $m) {
            $session = ClassSession::where('tenant_id', $m->tenant_id)->findOrFail((int) post('session_id'));
            $b = app(BookingService::class)->book($m, $session);

            return $b->status === 'booked' ? 'Reserva confirmada' : 'Quedaste en lista de espera';
        });
    }

    public function onCancel()
    {
        return $this->act(function (Member $m) {
            $booking = Booking::where('member_id', $m->id)->where('session_id', (int) post('session_id'))
                ->whereIn('status', ['booked', 'waitlist'])->firstOrFail();
            app(BookingService::class)->cancel($booking);

            return 'Reserva cancelada';
        });
    }

    protected function act(callable $fn): array
    {
        if (!$this->boot() || !$this->member) {
            throw new \ApplicationException('Ingresa con tu cuenta de socio.');
        }
        try {
            $msg = $fn($this->member);
        } catch (GymException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        $this->load();

        \Flash::success($msg);

        return ['#gym-agenda' => $this->renderPartial('@agenda')];
    }
}
