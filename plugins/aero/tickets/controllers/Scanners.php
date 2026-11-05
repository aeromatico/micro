<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\ScopesToTenant;
use Aero\Tickets\Models\Scanner;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Personal / dispositivos de escaneo: cada uno entra con su enlace y solo valida los eventos asignados. */
class Scanners extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'scanners');
    }

    /** El enlace en claro se muestra UNA vez (en la sesión), nunca queda guardado. */
    public function formAfterCreate($model)
    {
        if ($model->plainToken) {
            session()->flash('aero_tickets_scanner_link', ['id' => $model->id, 'url' => $model->url]);
        }
    }

    protected function scanner(): Scanner
    {
        $q = Scanner::query();
        $this->scopeToTenant($q);

        return $q->findOrFail((int) post('id'));
    }

    public function onRegenerate()
    {
        $s = $this->scanner();
        $s->issueToken();
        $s->saveQuietly();
        session()->flash('aero_tickets_scanner_link', ['id' => $s->id, 'url' => $s->url]);
        Flash::warning('Enlace nuevo generado: el anterior ya no funciona.');

        return \Redirect::refresh();
    }

    public function onToggle()
    {
        $s = $this->scanner();
        $s->is_active = !$s->is_active;
        $s->save();
        Flash::success($s->is_active ? 'Escáner activado.' : 'Escáner desactivado: su enlace ya no sirve.');

        return \Redirect::refresh();
    }
}
