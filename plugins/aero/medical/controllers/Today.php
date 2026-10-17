<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\ConsultationService;
use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Patient;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Agenda del día del profesional (reservas de Aero.Office) con acceso directo a
 * la consulta. Office es soft: sin él la pantalla avisa y no rompe.
 */
class Today extends Controller
{
    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'today');
    }

    public function index(): void
    {
        $this->pageTitle = 'Agenda de hoy';
        $this->vars['office'] = ConsultationService::officeAvailable();
        $this->vars['rows'] = [];

        if (!$this->vars['office']) {
            return;
        }

        $bookings = $this->bookingsQuery()
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereIn('status', ['pending', 'confirmed', 'in_service', 'completed'])
            ->with(['customer', 'worker'])->orderBy('starts_at')->get();

        $this->vars['rows'] = $bookings->map(fn ($b) => $this->row($b))->all();
    }

    public function booking($id = null): void
    {
        $b = $this->bookingOrFail($id);
        $this->pageTitle = 'Reserva ' . $b->code;
        $this->vars['row'] = $this->row($b);
    }

    public function booking_onStart($id = null)
    {
        return $this->start($this->bookingOrFail($id));
    }

    protected function start($booking)
    {
        $user = BackendAuth::getUser();
        $svc = app(ConsultationService::class);
        $kind = post('kind') === 'control' ? 'control' : 'consultation';

        try {
            $existing = Consultation::where('tenant_id', $booking->tenant_id)->where('booking_id', $booking->id)->first();
            if (!$existing) {
                if (!in_array($booking->status, ['confirmed', 'in_service'], true)) {
                    throw new MedicalException('Solo se inicia la consulta desde una reserva confirmada o en atención.');
                }
                $patient = $svc->patientForBooking($booking, post('patient_id') ? (int) post('patient_id') : null);
                $existing = $svc->start($patient, $kind, $user, ['booking' => $booking, 'worker_id' => $this->workerIdFor($booking, $user)]);
            }
        } catch (MedicalException | \Aero\Office\Classes\OfficeException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return \Backend::redirect('aero/medical/consultations/update/' . $existing->id);
    }

    protected function row($b): array
    {
        $patients = Patient::where('tenant_id', $b->tenant_id)->where('customer_id', $b->customer_id)->where('is_active', true)->orderBy('name')->get();
        $note = Consultation::where('tenant_id', $b->tenant_id)->where('booking_id', $b->id)->first();
        $linked = \Aero\Medical\Models\BookingPatient::where('tenant_id', $b->tenant_id)->where('booking_id', $b->id)->value('patient_id');

        return ['booking' => $b, 'patients' => $patients, 'note' => $note, 'linked' => $linked];
    }

    protected function bookingsQuery()
    {
        $q = \Aero\Office\Models\Booking::query();

        return CurrentTenant::isAdmin() ? $q : $q->visibleToUser();
    }

    protected function bookingOrFail($id)
    {
        if (!ConsultationService::officeAvailable()) {
            throw new \ApplicationException('Aero.Office no está instalado.');
        }

        return $this->bookingsQuery()->with(['customer', 'worker'])->whereKey((int) $id)->firstOrFail();
    }

    protected function workerIdFor($booking, $user): ?int
    {
        $w = \Aero\Office\Models\Worker::where('tenant_id', $booking->tenant_id)->where('user_id', $user->id)->value('id');

        return $w ?: ($booking->worker_id ?: null);
    }
}
