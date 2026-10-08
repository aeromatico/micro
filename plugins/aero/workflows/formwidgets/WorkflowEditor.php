<?php namespace Aero\Workflows\FormWidgets;

use Aero\Workflows\Classes\CurrentTenant;
use Aero\Workflows\Classes\NodeRegistry;
use Backend\Classes\FormWidgetBase;

/**
 * Editor visual del grafo (React Flow). El grafo viaja en un textarea oculto
 * como JSON; el modelo lo valida al guardar.
 */
class WorkflowEditor extends FormWidgetBase
{
    protected $defaultAlias = 'workfloweditor';

    public function render(): string
    {
        $this->prepareVars();

        return $this->makePartial('workfloweditor');
    }

    protected function prepareVars(): void
    {
        $value = $this->getLoadValue();
        $json = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) : (string) $value;

        $catalog = [];
        $tenantId = $this->model->tenant_id ?: (CurrentTenant::isAdmin() ? null : CurrentTenant::id());

        foreach (NodeRegistry::availableFor($tenantId ? (int) $tenantId : null) as $type => $node) {
            $catalog[$type] = [
                'label'    => $node['label'] ?? $type,
                'category' => $node['category'] ?? 'action',
                'fields'   => $node['fields'] ?? [],
                'handles'  => $node['handles'] ?? [],
                'note'     => $node['note'] ?? null,
            ];
        }

        $this->vars['graphJson']   = $json;
        $this->vars['fieldName']   = $this->getFieldName();
        $this->vars['editorId']    = $this->getId('mount');
        $this->vars['textareaId']  = $this->getId('graph');
        $this->vars['config']      = json_encode([
            'mountId'    => $this->getId('mount'),
            'textareaId' => $this->getId('graph'),
            'catalog'    => $catalog,
            'connectors' => $this->connectors(),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** Solo los Connectors del tenant del workflow (o todos, para el superadmin sin tenant). */
    protected function connectors(): array
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class) || !class_exists(\Aero\Sites\Models\Tenant::class)) {
            return [];
        }

        $tenantId = $this->model->tenant_id ?: (CurrentTenant::isAdmin() ? null : CurrentTenant::id());

        if (!$tenantId) {
            return [];
        }

        return \Aero\Connector\Models\Connector::where('owner_type', \Aero\Sites\Models\Tenant::class)
            ->where('owner_id', $tenantId)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getSaveValue($value): mixed
    {
        return $value ?: null;
    }

    protected function loadAssets(): void
    {
        $file = __DIR__ . '/workfloweditor/assets/flow-editor.js';
        $version = '?v=' . hash('crc32', (string) @filemtime($file));

        $this->addCss('flow-editor.css' . $version);
        $this->addJs('flow-editor.js' . $version);
    }
}
