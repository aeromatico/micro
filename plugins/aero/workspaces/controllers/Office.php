<?php namespace Aero\Workspaces\Controllers;

use Aero\Workspaces\Classes\AgentChat;
use Aero\Workspaces\Classes\CurrentTenant;
use Aero\Workspaces\Classes\Tasks;
use Aero\Workspaces\Classes\Workspace;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Oficina: el equipo del tenant (orquestador + contratados) trabajando a la
 * vista. Los encargos se registran de verdad; la ejecución es una simulación.
 */
class Office extends Controller
{
    public $requiredPermissions = ['aero.workspaces.use', 'aero.workspaces.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Workspaces', 'workspaces', 'office');
        $this->pageTitle = 'Oficina de agentes';
        $this->addCss('/plugins/aero/workspaces/assets/css/office.css?v=6');
        $this->addJs('/plugins/aero/workspaces/assets/js/office.js?v=6');
    }

    public function index(): void
    {
        $tenantId = CurrentTenant::id();
        $this->vars['hasTenant'] = (bool) $tenantId;

        if (!$tenantId) {
            $this->vars['data'] = null;

            return;
        }

        $team = Workspace::team($tenantId);
        $summary = Workspace::summary($tenantId);

        $this->vars['data'] = [
            'team'       => $team,
            'summary'    => $summary,
            'tasks'      => Tasks::recent($tenantId, 8),
            'base_points' => array_sum(array_column($team, 'task_fee')),
            'sprites'    => \Url::asset('plugins/aero/workspaces/assets/img/sprites.png'),
            'avatars'    => \Url::asset('plugins/aero/workspaces/assets/img/avatars.png'),
        ];
    }

    public function onSendTask(): array
    {
        $tenantId = $this->tenantOrFail();

        try {
            $task = Tasks::submit($tenantId, (string) post('brief'), BackendAuth::getUser()?->id, 'office');
        }
        catch (\DomainException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return ['task' => Tasks::payload($task), 'summary' => Workspace::summary($tenantId), 'tasks' => Tasks::recent($tenantId, 8)];
    }

    /** Conversación con un agente que trabaja de verdad (también sirve para consultar si ya respondió). */
    public function onAgentHistory(): array
    {
        try {
            return AgentChat::history($this->tenantOrFail(), (string) post('slug'));
        }
        catch (\DomainException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    public function onAgentSend(): array
    {
        try {
            return AgentChat::send($this->tenantOrFail(), BackendAuth::getUser()?->id, (string) post('slug'), (string) post('message'));
        }
        catch (\DomainException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    /** Estado actual (el reloj del servidor es el que manda). */
    public function onPoll(): array
    {
        $tenantId = $this->tenantOrFail();

        return ['summary' => Workspace::summary($tenantId), 'tasks' => Tasks::recent($tenantId, 8)];
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
