<?php namespace Aero\Chatbots\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Bots extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = ['aero.chatbots.manage', 'aero.chatbots.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Chatbots', 'chatbots', 'bots');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
        }
    }

    /**
     * A un tenant admin no le mostramos el selector de tenant (queda fijado
     * al suyo en formExtendModel); solo un superadmin sin tenant propio lo
     * puede elegir. Las opciones del dropdown en sí las resuelve el modelo
     * (ver Bot::getAccountIdOptions/getTenantIdOptions), porque FormField
     * solo consulta métodos del modelo, no del controller.
     */
    public function formExtendFields($form): void
    {
        if ($this->getCurrentTenantId()) {
            $form->removeField('tenant_id');
        }
    }
}
