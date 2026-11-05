<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\CurrentTenant;
use Aero\Tickets\Classes\ScopesToTenant;
use Aero\Tickets\Models\PaymentSetting;
use Backend\Classes\Controller;
use BackendMenu;

/** Cómo cobra el espacio de trabajo sus entradas (QR de aero/pay, transferencia, comprobante). Un registro por tenant. */
class PaymentSettings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\FormController::class];

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'payment_settings');
    }

    /** Abre directamente la configuración del espacio de trabajo actual (la crea vacía si no existe). */
    public function index()
    {
        $tid = CurrentTenant::id();
        if (!$tid) {
            \Flash::error('No hay un espacio de trabajo activo.');

            return \Backend::redirect('aero/tickets/events');
        }
        $s = PaymentSetting::where('tenant_id', $tid)->first() ?: PaymentSetting::create(['tenant_id' => $tid, 'proof_policy' => 'optional']);

        return \Backend::redirect('aero/tickets/paymentsettings/update/' . $s->id);
    }
}
