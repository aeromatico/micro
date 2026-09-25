<?php namespace Aero\Credits\Controllers;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Classes\Invitations;
use Aero\Credits\Models\CreditInvitation;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * "Mis invitaciones": el tenant ve su cuota, envía invitaciones por
 * WhatsApp/correo y ve el estado de las que ya mandó. Sin permiso: cualquier
 * usuario del panel con un tenant resoluble ve/usa SU cuota (nunca la de
 * otro), mismo patrón que Wallet.
 */
class MyInvitations extends Controller
{
    public $requiredPermissions = [];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'myinvitations', 'myinvitations');
        $this->pageTitle = 'Mis invitaciones';
    }

    public function index()
    {
        $tenantId = Credits::resolveCurrentTenantId();

        $this->vars['tenantId'] = $tenantId;
        $this->vars['invitations'] = collect();
        $this->vars['remaining'] = 0;
        $this->vars['allowed'] = 0;

        if (!$tenantId) {
            return;
        }

        $quota = Invitations::quotaFor($tenantId);

        $this->vars['allowed'] = $quota->invites_allowed;
        $this->vars['remaining'] = Invitations::remaining($tenantId);
        $this->vars['statusLabels'] = (new CreditInvitation)->getStatusOptions();
        $this->vars['invitations'] = CreditInvitation::where('tenant_id', $tenantId)
            ->orderByDesc('id')->get();
    }

    protected function tenantIdOrFail(): int
    {
        $tenantId = Credits::resolveCurrentTenantId();

        if (!$tenantId) {
            throw new ApplicationException('Elige primero un sitio (tenant).');
        }

        return $tenantId;
    }

    public function onSendInvitation()
    {
        $tenantId = $this->tenantIdOrFail();

        try {
            Invitations::create(
                $tenantId,
                BackendAuth::getUser()?->id,
                (string) post('channel', 'whatsapp'),
                (string) post('recipient', '')
            );
        }
        catch (\RuntimeException $e) {
            throw new ApplicationException($e->getMessage());
        }

        \Flash::success('Invitación enviada.');

        return $this->refreshInvitationsPartial($tenantId);
    }

    protected function refreshInvitationsPartial(int $tenantId)
    {
        $quota = Invitations::quotaFor($tenantId);
        $this->vars['allowed'] = $quota->invites_allowed;
        $this->vars['remaining'] = Invitations::remaining($tenantId);
        $this->vars['statusLabels'] = (new CreditInvitation)->getStatusOptions();
        $this->vars['invitations'] = CreditInvitation::where('tenant_id', $tenantId)->orderByDesc('id')->get();

        return ['#invitations-panel' => $this->makePartial('invitations_panel')];
    }
}
