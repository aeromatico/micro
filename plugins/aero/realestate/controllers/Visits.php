<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\DealService;
use Aero\Realestate\Classes\LeadService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\ScopesToTenant;
use Aero\Realestate\Classes\VisitService;
use Backend\Classes\Controller;
use BackendMenu;

class Visits extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'visits');
    }

    /** «Programar visita» desde una oportunidad llega con cliente, propiedad y lead en la URL. */
    public function formExtendModel($model)
    {
        if (!$model->exists) {
            foreach (['property_id', 'client_id', 'lead_id'] as $k) {
                if (get($k)) {
                    $model->{$k} = (int) get($k);
                }
            }
        }

        return $model;
    }

    protected function visit(): \Aero\Realestate\Models\Visit
    {
        return \Aero\Realestate\Models\Visit::visible()->findOrFail((int) post('id'));
    }

    protected function act(callable $fn, string $ok)
    {
        try {
            $fn($this->visit(), app(VisitService::class));
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success($ok);

        return \Redirect::refresh();
    }

    /** Programar desde el formulario pasa por el servicio: choques de agenda, etapa del lead y avisos. */
    public function create_onSave($context = null)
    {
        $data = (array) post('Visit');
        try {
            $tenant = \Aero\Realestate\Classes\CurrentTenant::isAdmin() ? null : \Aero\Realestate\Classes\CurrentTenant::id();
            $property = \Aero\Realestate\Models\Property::visible()->findOrFail((int) ($data['property_id'] ?? 0));
            \Aero\Realestate\Models\Client::visible()->findOrFail((int) ($data['client_id'] ?? 0));
            $visit = app(VisitService::class)->schedule([
                'property_id' => $property->id, 'client_id' => (int) $data['client_id'], 'starts_at' => $data['starts_at'] ?? null,
                'agent_id' => ($data['agent_id'] ?? '') !== '' ? (int) $data['agent_id'] : null,
                'lead_id' => ($data['lead_id'] ?? '') !== '' ? (int) $data['lead_id'] : null, 'notes' => $data['notes'] ?? null,
            ]);
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Visita programada.');

        return \Redirect::to(\Backend::url('aero/realestate/visits/update/' . $visit->id));
    }

    public function update_onRescheduleForm() { return $this->makePartial('popup_reschedule', ['m' => $this->visit()]); }
    public function update_onCancelForm() { return $this->makePartial('popup_cancel', ['m' => $this->visit()]); }
    public function update_onDoneForm() { return $this->makePartial('popup_done', ['m' => $this->visit()]); }

    public function update_onConfirm()
    {
        return $this->act(fn ($v, $s) => $s->confirm($v), 'Visita confirmada.');
    }

    public function update_onReschedule()
    {
        return $this->act(fn ($v, $s) => $s->reschedule($v, post('starts_at'), post('reason')), 'Visita reprogramada.');
    }

    public function update_onCancel()
    {
        return $this->act(fn ($v, $s) => $s->cancel($v, post('reason')), 'Visita cancelada.');
    }

    public function update_onDone()
    {
        return $this->act(fn ($v, $s) => $s->complete($v, post('notes')), 'Visita realizada.');
    }
}
