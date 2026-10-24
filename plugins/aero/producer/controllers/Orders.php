<?php namespace Aero\Producer\Controllers;

use Aero\Producer\Classes\CurrentTenant;
use Aero\Producer\Classes\ScopesToTenant;
use Aero\Producer\Classes\Billing;
use Aero\Producer\Classes\HeyGen;
use Aero\Producer\Classes\Production;
use Aero\Producer\Jobs\ProduceOrder;
use Aero\Producer\Models\Order;
use Aero\Producer\Models\Style;
use ApplicationException;
use Backend\Classes\Controller;
use BackendMenu;
use Event;
use Flash;

/** Pedidos de video: el tenant ve y edita solo los suyos (falla cerrado). */
class Orders extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.producer.use', 'aero.producer.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Producer', 'producer', 'orders');
    }

    /** `?style=ID` llega desde la galería; se ignora si el estilo no está activo. */
    public function formExtendModel($model): void
    {
        if (!$model->exists && ($id = (int) get('style')) && Style::active()->whereKey($id)->exists()) {
            $model->style_id = $id;
        }
    }

    /** Cada estilo pide lo suyo: el presentador una imagen, el explicativo un tema y fondo musical. */
    public function formExtendFields($form): void
    {
        $style = $form->model->style_id ? Style::find($form->model->style_id) : null;
        $kind = $style?->kind;

        if ($kind !== 'presenter') {
            $form->removeField('image');
            $form->removeField('resolution');
        }
        if ($kind !== 'explainer') {
            $form->removeField('extras[topic]');
            $form->removeField('extras[background]');
        }
        if (!CurrentTenant::isAdmin()) {
            foreach (['status', 'output_url', 'error_message'] as $field) {
                $form->removeField($field);
            }
        }
    }

    /**
     * Solo el administrador lanza la producción: cada video cuesta dinero real a la
     * plataforma y el cobro al tenant aún no está activo.
     */
    public function update_onProduce($recordId = null)
    {
        if (!CurrentTenant::isAdmin()) {
            throw new ApplicationException('Solo el administrador puede producir pedidos.');
        }

        $order = Order::findOrFail($recordId);
        if ($order->status !== 'queued' || !Production::canProduce($order)) {
            throw new ApplicationException('Solo se producen pedidos de presentador que estén en cola (ya cobrados).');
        }

        ProduceOrder::dispatch($order);
        Flash::success('Producción iniciada. El video aparece aquí en unos minutos.');

        return \Backend::redirect('aero/producer/orders/update/' . $order->id);
    }

    /** El administrador rechaza un pedido en cola (p. ej. un explicativo que no se puede hacer): devuelve los créditos. */
    public function update_onReject($recordId = null)
    {
        if (!CurrentTenant::isAdmin()) {
            throw new ApplicationException('Solo el administrador puede rechazar pedidos.');
        }

        $order = Order::findOrFail($recordId);
        if ($order->status !== 'queued') {
            throw new ApplicationException('Solo se rechazan pedidos en cola.');
        }

        Production::fail($order, 'No pudimos producir este pedido. Se devolvieron tus créditos.');
        Flash::success('Pedido rechazado y créditos devueltos.');

        return \Backend::redirect('aero/producer/orders/update/' . $order->id);
    }

    public function listExtendColumns($list): void
    {
        if (!CurrentTenant::isAdmin()) {
            $list->removeColumn('tenant_name');
        }
    }

    /** Un pedido ya en cola o en producción no se toca desde el formulario. */
    public function formBeforeSave($model): void
    {
        if ($model->exists && !$model->is_editable && !CurrentTenant::isAdmin()) {
            throw new ApplicationException('Este pedido ya se envió y no se puede modificar.');
        }
    }

    /** «Guardar y enviar»: guarda y, si está completo, lo deja en cola para producción. */
    public function formAfterSave($model): void
    {
        if (!post('send')) {
            return;
        }

        $model->refresh();
        if ($problems = $model->problems()) {
            throw new ApplicationException('Pedido guardado como borrador. ' . implode(' ', $problems));
        }

        // Primero se cobra: sin saldo el pedido sigue siendo borrador.
        Billing::charge($model);

        $model->forceFill(['status' => 'queued', 'requested_at' => now(), 'error_message' => null])->save();

        Event::fire('aero.producer.orderRequested', [$model]);

        // Presentador: ya cobrado, se produce solo. Sin llave de HeyGen queda en cola para el administrador.
        if (Production::canProduce($model) && HeyGen::platform()) {
            ProduceOrder::dispatch($model);
        }

        Flash::success('Pedido enviado. Te avisaremos cuando el video esté listo.');
    }
}
