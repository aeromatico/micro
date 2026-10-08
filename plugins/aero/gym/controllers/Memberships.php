<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Memberships extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'memberships');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el gimnasio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    protected function current($recordId): \Aero\Gym\Models\Membership
    {
        return $this->formFindModelObject($recordId);
    }

    protected function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Aero\Gym\Classes\GymException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    public function update_onMarkPaid($recordId = null)
    {
        $this->guard(fn () => app(\Aero\Gym\Classes\MembershipService::class)->markPaid($this->current($recordId)));
        \Flash::success('Membresía activada.');

        return \Redirect::refresh();
    }

    public function update_onIssueCharge($recordId = null)
    {
        $qr = app(\Aero\Gym\Classes\MembershipService::class)->issueCharge($this->current($recordId));
        if (!$qr) {
            throw new \ApplicationException('No se pudo generar el QR: configure la cuenta de cobro en Configuración (y que Aero Pay esté activo).');
        }
        \Flash::success('QR de cobro generado.');

        return \Redirect::refresh();
    }

    public function update_onSendCharge($recordId = null)
    {
        $m = $this->current($recordId);
        $qr = app(\Aero\Gym\Classes\MembershipService::class)->issueCharge($m);
        $text = 'Hola ' . $m->member->name . ', tu membresía ' . ($m->plan?->name ?? '') . ' por ' . number_format((float) $m->price, 2) . ' ' . $m->currency
            . ' vence el ' . $m->ends_on->format('d/m/Y') . '.' . ($qr ? ' Paga con el QR adjunto.' : '');
        $media = $qr && $qr->qr_image ? url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image') : null;

        if (!\Aero\Gym\Classes\Notifier::whatsapp($m->member, $text, $media)) {
            throw new \ApplicationException('No se pudo enviar: revise que el socio tenga teléfono y que WhatsApp esté conectado en Hello.');
        }
        \Flash::success('Cobro enviado por WhatsApp.');
    }

    public function update_onCancelMembership($recordId = null)
    {
        $this->guard(fn () => app(\Aero\Gym\Classes\MembershipService::class)->cancel($this->current($recordId), 'Cancelada desde el panel'));
        \Flash::success('Membresía cancelada.');

        return \Redirect::refresh();
    }

    public function update_onRenew($recordId = null)
    {
        $m = $this->current($recordId);
        $new = $this->guard(fn () => app(\Aero\Gym\Classes\MembershipService::class)->create($m->member, $m->plan, null, false, $m));

        return \Redirect::to(\Backend::url('aero/gym/memberships/update/' . $new->id));
    }
}
