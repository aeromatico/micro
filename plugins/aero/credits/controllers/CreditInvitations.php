<?php namespace Aero\Credits\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * Auditoría de plataforma: todas las invitaciones enviadas por todos los
 * tenants, con su estado. Solo lectura — la cuota se gestiona en
 * CreditInvitationQuotas; el envío lo hace el propio tenant en MyInvitations.
 */
class CreditInvitations extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'creditinvitations');
    }
}
