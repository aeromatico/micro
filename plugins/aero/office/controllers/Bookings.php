<?php namespace Aero\Office\Controllers;

use Aero\Office\Classes\Availability;
use Aero\Office\Classes\BookingService;
use Aero\Office\Classes\CurrentTenant;
use Aero\Office\Classes\OfficeException;
use Aero\Office\Models\Booking;
use Aero\Office\Models\Customer;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Reservas del negocio. Crear y cambiar de estado pasa SIEMPRE por
 * BookingService (disponibilidad, bloqueo e historial); el formulario nativo
 * solo sirve para capturar datos y editar notas internas.
 */
class Bookings extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.office.use', 'aero.office.reception', 'aero.office.professional', 'aero.office.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Office', 'office', 'bookings');
    }

    /** Falla cerrado: tenant propio; un profesional solo ve sus reservas. */
    public function listExtendQuery($query): void
    {
        $query->visibleToUser();
    }

    public function formExtendQuery($query): void
    {
        $query->visibleToUser();
    }

    /** Crear: NO se guarda el modelo directo; se reserva con BookingService. */
    public function create_onSave($context = null)
    {
        $user = BackendAuth::getUser();
        if (!$user->hasAccess('aero.office.use') && !$user->hasAccess('aero.office.reception') && !CurrentTenant::isAdmin()) {
            throw new \ApplicationException('No tiene permiso para crear reservas.');
        }

        $d = (array) post('Booking');
        $tenantId = CurrentTenant::isAdmin() ? (int) ($d['tenant_id'] ?? 0) : (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar el negocio.');
        }

        try {
            if (!empty($d['customer_id'])) {
                $customer = Customer::where('tenant_id', $tenantId)->find((int) $d['customer_id']);
                if (!$customer) {
                    throw new OfficeException('El cliente no es válido.');
                }
            } else {
                if (trim((string) ($d['new_name'] ?? '')) === '') {
                    throw new OfficeException('Elige un cliente o escribe el nombre del nuevo.');
                }
                $customer = Customer::findMatch($tenantId, $d['new_email'] ?? null, $d['new_phone'] ?? null)
                    ?: Customer::create([
                        'tenant_id' => $tenantId, 'name' => trim($d['new_name']),
                        'phone' => $d['new_phone'] ?? null, 'email' => ($d['new_email'] ?? '') ?: null,
                    ]);
            }

            if (empty($d['starts_at'])) {
                throw new OfficeException('Indica la fecha y la hora.');
            }

            $b = app(BookingService::class)->create($customer, [
                'branch_id'      => (int) ($d['branch_id'] ?? 0),
                'service_id'     => (int) ($d['service_id'] ?? 0),
                'worker_id'      => ($d['worker_id'] ?? '') !== '' ? (int) $d['worker_id'] : null,
                'starts_at'      => $d['starts_at'],
                'status'         => $d['status'] ?? null,
                'customer_notes' => $d['customer_notes'] ?? null,
                'internal_notes' => $d['internal_notes'] ?? null,
            ], 'manual', $user->id);
        } catch (OfficeException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success('Reserva ' . $b->code . ' creada.');

        return \Backend::redirect('aero/office/bookings/update/' . $b->id);
    }

    /** Editar: solo las notas internas (el resto cambia con acciones que dejan historial). */
    public function update_onSave($recordId = null, $context = null)
    {
        $b = $this->bookingOrFail($recordId);
        $d = (array) post('Booking');
        app(BookingService::class)->updateNotes($b, $d['internal_notes'] ?? null, BackendAuth::getUser()->id);
        \Flash::success('Notas guardadas.');

        return \Backend::redirect('aero/office/bookings/update/' . $b->id);
    }

    public function update_onConfirm($recordId = null)
    {
        return $this->act($recordId, fn ($b, $s, $uid) => $s->confirm($b, $uid), 'Reserva confirmada.', 'front');
    }

    public function update_onReject($recordId = null)
    {
        return $this->act($recordId, fn ($b, $s, $uid) => $s->reject($b, post('reason'), $uid), 'Reserva rechazada.', 'front');
    }

    public function update_onCancel($recordId = null)
    {
        return $this->act($recordId, fn ($b, $s, $uid) => $s->cancel($b, post('reason'), 'staff', $uid), 'Reserva cancelada; el horario quedó libre.', 'front');
    }

    public function update_onStart($recordId = null)
    {
        return $this->act($recordId, fn ($b, $s, $uid) => $s->startService($b, $uid), 'Atención iniciada.', 'own');
    }

    public function update_onComplete($recordId = null)
    {
        return $this->act($recordId, fn ($b, $s, $uid) => $s->complete($b, $uid), 'Atención completada.', 'own');
    }

    public function update_onNoShow($recordId = null)
    {
        return $this->act($recordId, fn ($b, $s, $uid) => $s->markNoShow($b, $uid), 'Marcada como «No asistió».', 'front');
    }

    public function update_onReschedule($recordId = null)
    {
        if (!post('starts_at')) {
            throw new \ApplicationException('Indica la nueva fecha y hora.');
        }

        return $this->act($recordId, fn ($b, $s, $uid) => $s->reschedule($b, post('starts_at'), null, 'staff', $uid), 'Reserva reprogramada.', 'front');
    }

    public function update_onReassign($recordId = null)
    {
        $worker = (int) post('worker_id');
        if (!$worker) {
            throw new \ApplicationException('Elige un profesional.');
        }

        return $this->act($recordId, fn ($b, $s, $uid) => $s->reassign($b, $worker, $uid), 'Reserva reasignada.', 'front');
    }

    /** Profesionales que pueden tomar esta reserva (para el selector de reasignación). */
    public function workerChoices(Booking $b): array
    {
        $service = $b->service;
        $branch = $b->branch;
        if (!$service || !$branch) {
            return [];
        }

        return Availability::eligibleWorkers($service, $branch)->pluck('name', 'id')->all();
    }

    protected function bookingOrFail($id): Booking
    {
        return Booking::visibleToUser()->findOrFail((int) $id);
    }

    /**
     * @param string $who front = administración/recepción; own = además el profesional dueño de la cita
     */
    protected function act($recordId, callable $fn, string $ok, string $who)
    {
        $user = BackendAuth::getUser();
        $b = $this->bookingOrFail($recordId);

        $front = CurrentTenant::isAdmin() || $user->hasAccess('aero.office.use') || $user->hasAccess('aero.office.reception');
        if (!$front && $who === 'front') {
            throw new \ApplicationException('No tiene permiso para esta acción.');
        }

        try {
            $fn($b, app(BookingService::class), $user->id);
        } catch (OfficeException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success($ok);

        return \Backend::redirect('aero/office/bookings/update/' . $b->id);
    }
}
