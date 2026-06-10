<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\ApiToken;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;

class ApiTokens extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.sites.manage_api_tokens'];

    protected ?string $lastPlainToken = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'apitokens');
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

    public function formBeforeCreate($model): void
    {
        ['plain' => $plain, 'hashed' => $hashed] = ApiToken::generateToken();
        $model->token = $hashed;
        $this->lastPlainToken = $plain;
    }

    public function formAfterCreate($model): void
    {
        if ($this->lastPlainToken) {
            Flash::success("Token creado. Cópialo ahora, no se mostrará de nuevo: {$this->lastPlainToken}");
        }
    }
}
