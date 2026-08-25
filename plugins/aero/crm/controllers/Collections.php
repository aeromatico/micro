<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Classes\Collections\CollectionReminderGenerator;
use Aero\Crm\Models\CollectionItem;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Collections extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_collections'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-cobranzas');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
            $model->status = 'pending';
        }
    }

    /**
     * Marca como pagados los cobros seleccionados en el listado.
     */
    public function onMarkAsPaid()
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            Flash::error('No se pudo determinar el tenant actual.');
            return;
        }

        $ids = post('checked', []);
        $items = CollectionItem::forTenant($tenantId)->whereIn('id', $ids)->get();

        foreach ($items as $item) {
            $item->markAsPaid();
        }

        Flash::success(count($items) . ' cobro(s) marcado(s) como pagado(s).');
        return $this->listRefresh();
    }

    /**
     * Envía manualmente el recordatorio de cobranza (WhatsApp vía Aero.Hello)
     * a los cobros seleccionados. Reutiliza el mismo generador que corre
     * automáticamente cada día (ver Plugin::registerSchedule).
     */
    public function onSendReminder()
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            Flash::error('No se pudo determinar el tenant actual.');
            return;
        }

        $generator = new CollectionReminderGenerator();
        $ids = post('checked', []);
        $items = CollectionItem::forTenant($tenantId)->whereIn('id', $ids)->get();

        $sent = 0;
        foreach ($items as $item) {
            if ($generator->sendReminder($item)) {
                $sent++;
            }
        }

        $skipped = count($items) - $sent;
        $message = "Recordatorio enviado a {$sent} contacto(s).";
        if ($skipped > 0) {
            $message .= " {$skipped} omitido(s) por no tener WhatsApp vinculado en Hello.";
        }

        Flash::success($message);
        return $this->listRefresh();
    }
}
