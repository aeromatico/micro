<?php namespace Aero\Shortcuts\ReportWidgets;

use Aero\Shortcuts\Classes\CurrentTenant;
use Aero\Shortcuts\Models\Item;
use Backend;
use Backend\Classes\ReportWidgetBase;

/**
 * Fila única de botones rápidos del tenant, arrastrables para reordenar
 * (el JS vive en assets/js/shortcuts.js y guarda con Items::onReorder).
 */
class Shortcuts extends ReportWidgetBase
{
    protected $defaultAlias = 'aero_shortcuts_row';

    public function render()
    {
        $tenantId = CurrentTenant::id();

        $this->vars['items'] = Item::active()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
        $this->vars['title'] = (string) $this->property('title', 'Atajos');
        $this->vars['showTitle'] = filter_var($this->property('show_title', false), FILTER_VALIDATE_BOOLEAN);
        $this->vars['reorderUrl'] = Backend::url('aero/shortcuts/items');
        $this->vars['manageUrl'] = Backend::url('aero/shortcuts/items');
        $this->vars['createUrl'] = Backend::url('aero/shortcuts/items/create');

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'show_title' => [
                'title'   => 'Mostrar título',
                'type'    => 'checkbox',
                'default' => false,
            ],
            'title' => [
                'title'   => 'Título',
                'type'    => 'string',
                'default' => 'Atajos',
            ],
        ];
    }
}
