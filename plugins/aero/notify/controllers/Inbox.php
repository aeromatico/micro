<?php namespace Aero\Notify\Controllers;

use Aero\Notify\Models\InboxMessage;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** Bandeja in-app del usuario de backend: solo ve y marca lo suyo. */
class Inbox extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.notify.view_inbox'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Notify', 'notify', 'notify-inbox');
    }

    public function index(): void
    {
        $this->pageTitle = 'Mis notificaciones';
        $this->asExtension('ListController')->index();
    }

    public function listExtendQuery($query): void
    {
        $query->where('user_id', BackendAuth::getUser()->id);
    }

    public function index_onMarkAllRead()
    {
        InboxMessage::forUser(BackendAuth::getUser()->id)->unread()->update(['read_at' => now()]);

        return $this->listRefresh();
    }
}
