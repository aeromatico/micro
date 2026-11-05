<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Guests extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.use', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets', 'guests');
    }

    /** Invitado del tenant (falla cerrado) para las acciones AJAX. */
    protected function guest(): \Aero\Tickets\Models\Guest
    {
        $q = \Aero\Tickets\Models\Guest::query();
        $this->scopeToTenant($q);

        return $q->findOrFail((int) post('id'));
    }

    protected function checked()
    {
        $q = \Aero\Tickets\Models\Guest::query();
        $this->scopeToTenant($q);

        return $q->whereIn('id', array_map('intval', (array) post('checked')));
    }

    public function onRevoke()
    {
        $n = $this->guest()->revoke(post('reason'));
        \Flash::success('Invitación revocada.' . ($n ? " Se anularon {$n} entrada(s) sin usar." : ''));

        return \Redirect::refresh();
    }

    public function onRestore()
    {
        $this->guest()->restore();
        \Flash::success('Invitación restaurada: vuelve a quedar sin respuesta.');

        return \Redirect::refresh();
    }

    /** Marca la invitación como enviada (al abrir WhatsApp o compartir el enlace). */
    public function onMarkSent()
    {
        $g = $this->guest();
        if (!$g->invited_at) {
            $g->invited_at = now();
            $g->save();
        }

        return ['sent' => true];
    }

    public function onMarkSentChecked()
    {
        $n = $this->checked()->where('status', 'active')->whereNull('invited_at')->update(['invited_at' => now()]);
        \Flash::success("{$n} invitación(es) marcadas como enviadas.");

        return $this->listRefresh();
    }

    public function onRevokeChecked()
    {
        $n = 0;
        foreach ($this->checked()->where('status', 'active')->get() as $g) {
            $g->revoke();
            $n++;
        }
        \Flash::success("{$n} invitación(es) revocadas.");

        return $this->listRefresh();
    }
}
