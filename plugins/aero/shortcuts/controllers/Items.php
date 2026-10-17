<?php namespace Aero\Shortcuts\Controllers;

use Aero\Shortcuts\Classes\CurrentTenant;
use Aero\Shortcuts\Classes\ScopesToTenant;
use Aero\Shortcuts\Models\Item;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Items extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.shortcuts.use', 'aero.shortcuts.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shortcuts', 'shortcuts', 'items');
    }

    public function formBeforeCreate($model): void
    {
        $model->tenant_id = CurrentTenant::id();
    }

    public function listExtendQuery($query): void
    {
        $this->scopeToTenant($query);
        $query->orderBy('sort_order');
    }

    /** Guarda el orden que dejó el arrastre en el dashboard: ids en el orden nuevo. */
    public function onReorder()
    {
        $ids = array_values(array_filter(array_map('intval', (array) post('ids'))));
        $items = $this->scopeToTenant(Item::query())->whereIn('id', $ids)->get()->keyBy('id');

        $position = 0;
        foreach ($ids as $id) {
            if ($item = $items->get($id)) {
                Item::where('id', $id)->update(['sort_order' => ++$position]);
            }
        }

        return ['ok' => true];
    }

    /** Crea botones de muestra para el tenant actual. */
    public function onSeedExamples()
    {
        $created = static::seedExamples(CurrentTenant::id());
        Flash::success($created ? "Se crearon {$created} atajos de ejemplo." : 'Ya tienes atajos; no se agregó nada.');

        return redirect()->refresh();
    }

    public static function seedExamples(?int $tenantId): int
    {
        if (Item::where('tenant_id', $tenantId)->exists()) {
            return 0;
        }

        $examples = [
            ['Mis mensajes', 'list', 'aero/sms/messages', null, 'icon-envelope', '#2563eb'],
            ['Nueva plantilla SMS', 'form', 'aero/sms/templates', null, 'icon-plus', '#16a34a'],
            ['Ayuda de Market', 'url', null, 'https://market.com.bo', 'icon-life-ring', '#d97706'],
            ['Documentación', 'url', null, 'https://market.com.bo/documentacion', 'icon-book', '#7c3aed'],
        ];

        foreach ($examples as $i => [$label, $kind, $target, $url, $icon, $color]) {
            Item::create([
                'tenant_id' => $tenantId, 'label' => $label, 'kind' => $kind, 'target' => $target,
                'url' => $url, 'icon' => $icon, 'color' => $color, 'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }

        return count($examples);
    }
}
