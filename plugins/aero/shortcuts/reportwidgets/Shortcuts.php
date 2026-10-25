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

        $items = Item::active()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        // Los atajos heredados de la plantilla pueden apuntar a pantallas que el plan o el
        // rol de este usuario no incluye: no se muestran botones que acaban en "denegado".
        $allowed = array_map(fn ($p) => implode('/', array_slice(explode('/', $p), 0, 2)), array_keys((new Item)->getTargetOptions()));
        $items = $items->filter(function (Item $item) use ($allowed) {
            if ($item->kind === 'url') {
                return true;
            }

            return in_array(implode('/', array_slice(explode('/', trim((string) $item->target, '/')), 0, 2)), $allowed, true);
        })->values();
        $this->vars['items'] = $items;
        $this->vars['reorderUrl'] = Backend::url('aero/shortcuts/items');
        $this->vars['manageUrl'] = Backend::url('aero/shortcuts/items');
        $this->vars['createUrl'] = Backend::url('aero/shortcuts/items/create');

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'title' => [
                'title'   => 'Título',
                'type'    => 'string',
                'default' => 'Atajos',
                'description' => 'Déjalo vacío para ocultarlo. Los botones se editan en Atajos › Mis atajos (fuera del modo edición del dashboard).',
            ],
        ];
    }
}
