<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\EventService;
use Aero\Church\Classes\ScopesToTenant;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\EventRegistration;
use Aero\Church\Models\Member;
use Backend\Classes\Controller;
use BackendMenu;

class Events extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'events');
    }

    protected function findEvent($id): ChurchEvent
    {
        return ChurchEvent::visible()->findOrFail((int) $id);
    }

    protected function findReg(ChurchEvent $event, $id): EventRegistration
    {
        return $event->registrations()->findOrFail((int) $id);
    }

    protected function attempt(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success($ok);

        return \Redirect::refresh();
    }

    public function update_onRegisterMember($recordId = null)
    {
        $event = $this->findEvent($recordId);
        $member = Member::visible()->find((int) post('member_id'));
        if (!$member) {
            throw new \ApplicationException('Elige un miembro de la lista.');
        }

        return $this->attempt(fn () => app(EventService::class)->register($event, $member), 'Inscripción registrada.');
    }

    public function update_onRegisterGuest($recordId = null)
    {
        $event = $this->findEvent($recordId);

        return $this->attempt(fn () => app(EventService::class)->register($event, null, post('guest_name'), post('guest_phone')), 'Invitado inscrito.');
    }

    public function update_onCancelRegistration($recordId = null)
    {
        $event = $this->findEvent($recordId);
        $reg = $this->findReg($event, post('id'));

        return $this->attempt(fn () => app(EventService::class)->cancel($reg), 'Inscripción cancelada.');
    }

    public function update_onCheckIn($recordId = null)
    {
        $event = $this->findEvent($recordId);
        $reg = $this->findReg($event, post('id'));

        return $this->attempt(fn () => app(EventService::class)->checkIn($reg), 'Llegada registrada.');
    }

    public function update_onUndoCheckIn($recordId = null)
    {
        $event = $this->findEvent($recordId);
        $reg = $this->findReg($event, post('id'));

        return $this->attempt(fn () => app(EventService::class)->undoCheckIn($reg), 'Llegada deshecha.');
    }

    /** Escaneo del QR personal (un lector USB escribe el código y envía Enter). */
    public function update_onCheckInToken($recordId = null)
    {
        $event = $this->findEvent($recordId);

        return $this->attempt(fn () => app(EventService::class)->checkInByToken($event, (string) post('token')), 'Llegada registrada.');
    }

    public function update_onWalkIn($recordId = null)
    {
        $event = $this->findEvent($recordId);
        $member = post('member_id') ? Member::visible()->find((int) post('member_id')) : null;

        return $this->attempt(fn () => app(EventService::class)->walkIn($event, $member, post('guest_name')), 'Llegada registrada.');
    }
}
