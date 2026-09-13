<?php namespace Aero\Notify\Controllers;

use Aero\Notify\Traits\ScopesToTenant;
use ApplicationException;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Plantillas por evento, canal e idioma. tenant_id = 0 es la plantilla
 * global; una fila con el tenant_id de alguien la sobreescribe (ver
 * Template::resolveFor). A diferencia de Rules, acá no se separa el permiso
 * de global vs propia del tenant: todo cae bajo manage_templates.
 */
class Templates extends Controller
{
    use ScopesToTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.notify.manage_templates'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Aero.Notify', 'notify', 'notify-templates');
    }

    public function formExtendModel($model)
    {
        if (!$model->exists && !$this->isSuperadmin()) {
            $model->tenant_id = $this->effectiveTenantId();
        }

        return $model;
    }

    public function formBeforeSave($model): void
    {
        if (!$this->isSuperadmin() && (int) $model->tenant_id !== $this->effectiveTenantId()) {
            throw new ApplicationException('No podés modificar plantillas de otro tenant.');
        }
    }
}
