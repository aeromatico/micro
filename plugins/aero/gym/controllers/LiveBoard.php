<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\BookingService;
use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\GymException;
use Aero\Gym\Models\Booking;
use Aero\Gym\Models\ClassSession;
use Backend\Classes\Controller;
use BackendMenu;
use Response;

/**
 * Pantalla de clases en tiempo real. Sondea `data` cada 3 s con la versión
 * que ya tiene: si nada cambió responde sin payload. La versión sale de
 * `updated_at` de las sesiones, que BookingService toca en cada reserva,
 * cancelación, ascenso de lista de espera y asistencia.
 */
class LiveBoard extends Controller
{
    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public $pageTitle = 'Clases en vivo';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'live');
    }

    public function index(): void
    {
    }

    protected function sessions()
    {
        $q = ClassSession::visible()->with(['classType', 'instructor'])
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<=', now()->endOfDay())
            ->where('ends_at', '>=', now()->subMinutes(30))
            ->orderBy('starts_at');

        return $q->get();
    }

    public function data()
    {
        $sessions = $this->sessions();
        $version = md5($sessions->map(fn ($s) => $s->id . '@' . $s->updated_at?->timestamp)->implode('|') . '#' . $sessions->count());

        if (request()->query('v') === $version) {
            return Response::json(['changed' => false, 'v' => $version]);
        }

        $bookings = Booking::with('member')->whereIn('session_id', $sessions->pluck('id'))
            ->whereIn('status', ['booked', 'attended', 'waitlist', 'no_show'])
            ->orderBy('booked_at')->get()->groupBy('session_id');

        return Response::json([
            'changed'  => true,
            'v'        => $version,
            'now'      => now()->format('H:i:s'),
            'sessions' => $sessions->map(function ($s) use ($bookings) {
                $rows = $bookings[$s->id] ?? collect();
                $seated = $rows->whereIn('status', Booking::SEATED);

                return [
                    'id'         => $s->id,
                    'name'       => $s->classType?->name,
                    'color'      => $s->classType?->color,
                    'instructor' => $s->instructor?->name,
                    'room'       => $s->room,
                    'starts'     => $s->starts_at->format('H:i'),
                    'ends'       => $s->ends_at->format('H:i'),
                    'live'       => $s->starts_at->isPast() && $s->ends_at->isFuture(),
                    'capacity'   => $s->capacity,
                    'booked'     => $seated->count(),
                    'attended'   => $rows->where('status', 'attended')->count(),
                    'waitlist'   => $rows->where('status', 'waitlist')->count(),
                    'members'    => $rows->map(fn ($b) => ['booking' => $b->id, 'name' => $b->member?->name, 'status' => $b->status])->values(),
                ];
            })->values(),
        ]);
    }

    public function onCheckIn()
    {
        $this->perform(fn (BookingService $svc, Booking $b) => $svc->checkIn($b));
    }

    public function onNoShow()
    {
        $this->perform(fn (BookingService $svc, Booking $b) => $svc->markNoShow($b));
    }

    public function onCancelBooking()
    {
        $this->perform(fn (BookingService $svc, Booking $b) => $svc->cancel($b, false));
    }

    protected function perform(callable $fn): void
    {
        $booking = Booking::visible()->findOrFail((int) post('booking_id'));

        try {
            $fn(app(BookingService::class), $booking);
        } catch (GymException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }
}
