<?php namespace Aero\Workspaces\Controllers;

use Aero\Workspaces\Classes\CurrentTenant;
use Aero\Workspaces\Classes\Hiring;
use Aero\Workspaces\Classes\Workspace;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Mercado de agentes del tenant: ver el catálogo, contratar, ver su equipo y
 * sus skills. Todo se resuelve por el tenant de quien mira (nunca por la
 * petición) y comparte lógica con las herramientas del MCP.
 */
class Market extends Controller
{
    public $requiredPermissions = ['aero.workspaces.use', 'aero.workspaces.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Workspaces', 'workspaces', 'market');
        $this->pageTitle = 'Mercado de agentes';
        $this->addCss('/plugins/aero/workspaces/assets/css/market.css?v=6');
        $this->addJs('/plugins/aero/workspaces/assets/js/market.js?v=10');
    }

    public function index(): void
    {
        $tenantId = CurrentTenant::id();
        $this->vars['hasTenant'] = (bool) $tenantId;

        $this->vars['data'] = $tenantId ? [
            'agents'  => Workspace::market($tenantId),
            'team'    => Workspace::team($tenantId),
            'skills'  => Workspace::skills($tenantId),
            'summary' => Workspace::summary($tenantId),
            'avatars' => \Url::asset('plugins/aero/workspaces/assets/img/avatars.png'),
        ] : null;
    }

    public function onHire(): array
    {
        $tenantId = $this->tenantOrFail();

        try {
            $result = Hiring::hire($tenantId, (string) post('slug'), BackendAuth::getUser()?->id);
        }
        catch (\DomainException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return $result + ['team' => Workspace::team($tenantId), 'summary' => Workspace::summary($tenantId)];
    }

    public function onDismiss(): array
    {
        $tenantId = $this->tenantOrFail();

        try {
            $result = Hiring::dismiss($tenantId, (string) post('slug'));
        }
        catch (\DomainException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return $result + ['team' => Workspace::team($tenantId), 'summary' => Workspace::summary($tenantId)];
    }

    public function onCreateSkill(): array
    {
        $tenantId = $this->tenantOrFail();

        try {
            return ['skill' => Workspace::createSkill($tenantId, (string) post('name'), (string) post('description'))];
        }
        catch (\DomainException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    protected function tenantOrFail(): int
    {
        $tenantId = CurrentTenant::id();

        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar tu cuenta (tenant).');
        }

        return $tenantId;
    }
}
