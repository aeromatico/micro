<?php namespace Aero\Mcp\Controllers;

use Aero\Api\Traits\ScopesToOwner;
use Aero\Mcp\Models\McpKey;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/**
 * Keys dedicadas al MCP, bajo el menú «API & MCP». Se guardan como ApiKey
 * normales (mismo secreto, misma autenticación) pero solo con scopes MCP.
 */
class McpKeys extends Controller
{
    use ScopesToOwner;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.mcp.manage_keys'];

    protected ?string $plainKey = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Api', 'configuracion', 'mcpkeys');
    }

    public function listExtendQuery($query): void
    {
        $query->mcpOnly();
        $this->scopeToOwner($query);
    }

    public function formExtendQuery($query): void
    {
        $query->mcpOnly();
        $this->scopeToOwner($query);
    }

    public function formExtendModel($model)
    {
        if (!$model->exists && !$model->expires_at) {
            $model->expires_at = now()->addDays(90);
        }

        return $model;
    }

    public function formExtendFields($form): void
    {
        // El tenant_admin siempre emite para su propio tenant.
        if ($this->resolveOwner()) {
            $form->removeField('owner_id');
        }
    }

    public function formBeforeSave($model): void
    {
        // Lo que no es un módulo (mcp.use, scopes por tool, ajenos) se conserva tal cual;
        // esta pantalla solo cambia los módulos.
        $previous = $model->exists ? (array) $model->getOriginal('scopes') : [];
        $kept = array_values(array_filter($previous, fn ($s) => !McpKey::isModuleScope($s)));
        $modules = array_values(array_filter((array) $model->scopes, fn ($s) => McpKey::isModuleScope($s)));

        $model->scopes = array_values(array_unique(array_merge(['mcp.use'], $kept, $modules)));

        if ($owner = $this->resolveOwner()) {
            $model->owner_type = $owner['type'];
            $model->owner_id = $owner['id'];
        } elseif ($model->owner_id) {
            $model->owner_type = \Aero\Sites\Models\Tenant::class;
        }

        if (!$model->exists) {
            $this->plainKey = $model->issueSecret();
        }
    }

    public function formAfterCreate($model): void
    {
        Flash::success(trans('aero.api::lang.flash.created', ['key' => $this->plainKey]));
    }

    public function update_onRevealKey($recordId = null)
    {
        $plain = $this->findForOwner(McpKey::class, $recordId)->getPlainKey();

        if (!$plain) {
            Flash::error(trans('aero.api::lang.flash.not_recoverable'));
            return;
        }

        return ['key' => $plain];
    }

    public function update_onRegenerateKey($recordId = null)
    {
        $plain = $this->findForOwner(McpKey::class, $recordId)->regenerate();

        Flash::success(trans('aero.api::lang.flash.regenerated'));

        return ['key' => $plain];
    }

    public function update_onRevokeKey($recordId = null)
    {
        $this->findForOwner(McpKey::class, $recordId)->revoke();

        Flash::success(trans('aero.api::lang.flash.revoked'));

        return $this->listRefresh();
    }
}
