<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\DealService;
use Aero\Realestate\Classes\LeadService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\ScopesToTenant;
use Aero\Realestate\Classes\VisitService;
use Backend\Classes\Controller;
use BackendMenu;

class Leads extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'leads');
    }

    protected function lead(): \Aero\Realestate\Models\Lead
    {
        return \Aero\Realestate\Models\Lead::visible()->findOrFail((int) post('id'));
    }

    protected function act(callable $fn, string $ok)
    {
        try {
            $fn($this->lead());
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success($ok);

        return \Redirect::refresh();
    }

    public function update_onContactForm() { return $this->makePartial('popup_contact', ['m' => $this->lead()]); }
    public function update_onStageForm() { return $this->makePartial('popup_stage', ['m' => $this->lead()]); }
    public function update_onCloseForm() { return $this->makePartial('popup_close', ['m' => $this->lead()]); }

    public function update_onContact()
    {
        return $this->act(fn ($l) => app(LeadService::class)->logContact($l, (string) post('type', 'note'), (string) post('body'), post('next') ?: null), 'Contacto registrado.');
    }

    public function update_onStage()
    {
        return $this->act(fn ($l) => app(LeadService::class)->moveTo($l, (string) post('stage'), post('note')), 'Etapa actualizada.');
    }

    public function update_onClose()
    {
        return $this->act(function ($l) {
            $offer = \Aero\Realestate\Models\Offer::visible()->findOrFail((int) post('offer_id'));
            $amount = post('amount');
            app(DealService::class)->close($l, $offer, $amount === null || $amount === '' ? null : (float) $amount);
        }, 'Operación cerrada.');
    }
}
