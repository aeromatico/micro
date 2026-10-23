<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\CareService;
use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\ScopesToTenant;
use Aero\Church\Models\Visit;
use Aero\Church\Models\VisitContact;
use Backend\Classes\Controller;
use BackendMenu;

class Visits extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'visits');
    }

    public function update_onAddContact($recordId = null)
    {
        $visit = Visit::visible()->findOrFail((int) $recordId);
        if (!in_array(post('channel'), array_keys(VisitContact::CHANNELS), true)) {
            throw new \ApplicationException('Elige cómo lo contactaste.');
        }
        $date = post('next_action_date');
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \ApplicationException('Fecha no válida.');
        }

        VisitContact::create([
            'tenant_id' => $visit->tenant_id, 'visit_id' => $visit->id, 'channel' => post('channel'),
            'note' => mb_substr((string) post('note'), 0, 1000) ?: null,
            'next_action' => mb_substr((string) post('next_action'), 0, 200) ?: null, 'next_action_date' => $date ?: null,
        ]);

        \Flash::success('Contacto registrado.');
        return \Redirect::refresh();
    }

    public function update_onIntegrate($recordId = null)
    {
        $visit = Visit::visible()->findOrFail((int) $recordId);
        try {
            app(CareService::class)->integrate($visit);
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Ahora es un miembro nuevo. Completa sus datos en Miembros.');

        return \Redirect::refresh();
    }
}
