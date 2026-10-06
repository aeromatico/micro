<?php namespace Aero\Tracking\Controllers;

use Aero\Tracking\Classes\CurrentTenant;
use Aero\Tracking\Classes\Feeds\FeedService;
use Aero\Tracking\Classes\ScopesToTenant;
use Aero\Tracking\Models\Feed;
use ApplicationException;
use Backend;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/** Seguimiento de enlaces públicos (PedidosYa, ...). El alta pasa siempre por FeedService. */
class Feeds extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tracking.use', 'aero.tracking.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tracking', 'tracking', 'feeds');
    }

    public function create_onSave($context = null)
    {
        $data = (array) post('Feed');
        $tenantId = CurrentTenant::isAdmin() ? (int) ($data['tenant_id'] ?? 0) ?: null : CurrentTenant::id();

        if (!CurrentTenant::isAdmin() && !$tenantId) {
            throw new ApplicationException('Tu usuario no tiene un tenant asignado.');
        }

        try {
            $feed = FeedService::add($tenantId, (string) ($data['url'] ?? ''), $data['label'] ?? null, BackendAuth::getUser()?->id);
        } catch (\InvalidArgumentException $e) {
            throw new ApplicationException($e->getMessage());
        }

        Flash::success('Seguimiento iniciado.');

        return Backend::redirect('aero/tracking/feeds/update/' . $feed->id);
    }

    public function update_onPollNow($recordId)
    {
        $feed = $this->scopeToTenant(Feed::query())->findOrFail($recordId);
        FeedService::poll($feed);
        Flash::success('Actualizado.');

        return Backend::redirect('aero/tracking/feeds/update/' . $recordId);
    }

    public function update_onStop($recordId)
    {
        FeedService::stop($this->scopeToTenant(Feed::query())->findOrFail($recordId));
        Flash::success('Seguimiento detenido.');

        return Backend::redirect('aero/tracking/feeds/update/' . $recordId);
    }

    public function listExtendColumns($list): void
    {
        if (!CurrentTenant::isAdmin()) {
            $list->removeColumn('tenant_id');
        }
    }

    public function formExtendFields($form): void
    {
        if (!CurrentTenant::isAdmin()) {
            $form->removeField('tenant_id');
        }
    }
}
