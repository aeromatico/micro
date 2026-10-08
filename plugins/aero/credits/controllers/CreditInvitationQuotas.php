<?php namespace Aero\Credits\Controllers;

use Aero\Credits\Classes\Invitations;
use Aero\Credits\Models\CreditInvitationQuota;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/**
 * "Cuotas de invitación": cuántas invitaciones puede enviar cada tenant y
 * con qué plan/periodo se benefician sus invitados. El superadmin la
 * reasigna en cualquier momento; sin fila propia, un tenant usa los valores
 * por defecto de Créditos → Ajustes (ver Invitations::quotaFor()).
 */
class CreditInvitationQuotas extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'creditinvitationquotas');
    }

    public function onLoadQuotaForm()
    {
        $tenantId = (int) post('tenant_id');
        $this->vars['quota'] = $tenantId ? Invitations::quotaFor($tenantId) : new CreditInvitationQuota;
        $this->vars['tenants'] = $this->tenantOptions();

        return $this->makePartial('quota_form');
    }

    public function onSaveQuota()
    {
        $tenantId = (int) post('tenant_id');

        if (!$tenantId) {
            throw new ApplicationException('Elige un tenant.');
        }

        Invitations::setQuota($tenantId, [
            'invites_allowed'    => (int) post('invites_allowed', 3),
            'grant_plan_id'      => post('grant_plan_id') ?: null,
            'grant_period_unit'  => post('grant_period_unit') ?: null,
            'grant_period_count' => post('grant_period_count') ?: null,
        ] + ['updated_by' => BackendAuth::getUser()->id]);

        Flash::success('Cuota actualizada.');

        return $this->listRefresh();
    }

    protected function tenantOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all()
            : [];
    }
}
