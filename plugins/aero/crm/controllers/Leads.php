<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Models\Deal;
use Aero\Crm\Models\Lead;
use Aero\Crm\Models\Pipeline;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Redirect;

class Leads extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Config del formulario de Deal, reutilizada por la acción create para
     * que "Nuevo lead" guarde un Deal directo en el pipeline sin cambiar de
     * URL (sigue siendo /backend/aero/crm/leads/create).
     */
    const DEAL_FORM_CONFIG = '$/aero/crm/controllers/deals/config_form.yaml';

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_leads'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-leads');
    }

    /**
     * Solo para la acción create (tanto el render como el guardado AJAX)
     * cambiamos el config del formulario por el de Deal: mismos campos,
     * mismas validaciones y mismo comportamiento, pero bajo la URL de leads.
     */
    public function beforeDisplay()
    {
        if ($this->action === 'create') {
            $this->asExtension('FormController')->setConfig(
                self::DEAL_FORM_CONFIG,
                ['modelClass', 'form']
            );
        }
    }

    /**
     * Al guardar el alta (que es un Deal) volvemos a la lista de leads en vez
     * del formulario de Deal, para no quedar en la pantalla del registro.
     */
    public function formGetRedirectUrl($context = null, $model = null)
    {
        if ($this->action === 'create') {
            return \Backend::url('aero/crm/leads');
        }

        return null;
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if ($model->exists) {
            return;
        }

        $model->tenant_id = $this->getCurrentTenantId();

        if ($model instanceof Deal) {
            $pipeline = Pipeline::seedDefaultForTenant($model->tenant_id);
            $model->pipeline_id = $pipeline->id;
            $model->stage_id = $pipeline->stages()->orderBy('sort_order')->value('id');
        }
    }

    /**
     * El alta guarda el Deal del pipeline y además deja su registro en
     * aero_crm_leads (vinculado al deal/contacto), para que la lista de Leads
     * siga creciendo como antes. Al estar ya convertido, el lead queda
     * enlazado y "Convertir" no duplica nada.
     */
    public function formAfterCreate($model)
    {
        if (!$model instanceof Deal) {
            return;
        }

        $contact = $model->contact;

        Lead::create([
            'tenant_id'            => $model->tenant_id,
            'name'                 => $model->title,
            'email'                => $contact?->email,
            'phone'                => $contact?->phone,
            'company_name'         => $model->company?->name,
            'status'               => $model->status === 'lost' ? 'disqualified' : 'qualified',
            'owner_id'             => $model->owner_id,
            'converted_contact_id' => $model->contact_id,
            'converted_deal_id'    => $model->id,
            'in_pipeline'          => (bool) $model->in_pipeline,
        ]);
    }

    public function onConvert($recordId = null)
    {
        $lead = Lead::forTenant($this->getCurrentTenantId())->findOrFail($recordId ?: post('record_id'));
        $result = $lead->convert();

        Flash::success('Lead convertido en contacto y deal.');

        return Redirect::to(\Backend::url('aero/crm/deals/board') . '?deal=' . $result['deal']->id);
    }
}
