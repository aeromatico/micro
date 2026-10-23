<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\AnnouncementService;
use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\ScopesToTenant;
use Aero\Church\Models\Announcement;
use Backend\Classes\Controller;
use BackendMenu;

class Announcements extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'announcements');
    }

    /** Envío explícito y con confirmación: nada sale por WhatsApp sin que alguien lo pida. */
    public function update_onSend($recordId = null)
    {
        $a = Announcement::visible()->findOrFail((int) $recordId);
        try {
            app(AnnouncementService::class)->queue($a);
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Comunicado en envío. Verás el resultado en unos momentos.');

        return \Redirect::refresh();
    }
}
