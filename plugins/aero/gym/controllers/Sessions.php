<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Sessions extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'sessions');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el gimnasio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    protected function rosterBooking($id): \Aero\Gym\Models\Booking
    {
        // La reserva debe pertenecer a una clase visible para este usuario.
        return \Aero\Gym\Models\Booking::visible()->findOrFail((int) $id);
    }

    protected function refreshRoster($recordId)
    {
        $this->initForm($this->formFindModelObject($recordId));

        return ['#gym-roster' => $this->makePartial('roster_actions')];
    }

    protected function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Aero\Gym\Classes\GymException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    public function update_onBook($recordId = null)
    {
        $session = $this->formFindModelObject($recordId);
        $member = \Aero\Gym\Models\Member::visible()->findOrFail((int) post('member_id'));
        $b = $this->guard(fn () => app(\Aero\Gym\Classes\BookingService::class)->book($member, $session));
        \Flash::success($b->status === 'booked' ? 'Socio inscrito.' : 'Clase llena: el socio quedó en lista de espera.');

        return $this->refreshRoster($recordId);
    }

    public function update_onCancelBooking($recordId = null)
    {
        $this->guard(fn () => app(\Aero\Gym\Classes\BookingService::class)->cancel($this->rosterBooking(post('booking_id')), false));

        return $this->refreshRoster($recordId);
    }

    public function update_onCheckIn($recordId = null)
    {
        $this->guard(fn () => app(\Aero\Gym\Classes\BookingService::class)->checkIn($this->rosterBooking(post('booking_id'))));

        return $this->refreshRoster($recordId);
    }

    public function update_onNoShow($recordId = null)
    {
        $this->guard(fn () => app(\Aero\Gym\Classes\BookingService::class)->markNoShow($this->rosterBooking(post('booking_id'))));

        return $this->refreshRoster($recordId);
    }

    public function update_onCancelSession($recordId = null)
    {
        $n = app(\Aero\Gym\Classes\BookingService::class)->cancelSession($this->formFindModelObject($recordId));
        \Flash::success("Clase cancelada ({$n} reservas anuladas).");

        return \Redirect::refresh();
    }
}
