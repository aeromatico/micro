<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\ScopesToTenant;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Models\Contract;
use Backend\Classes\Controller;
use BackendMenu;

class Contracts extends Controller
{
    use ScopesToTenant;

    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'contracts');
    }

    protected function contract(): Contract
    {
        return Contract::visible()->findOrFail((int) post('id'));
    }

    protected function popup(string $partial): string
    {
        return $this->makePartial($partial, ['c' => $this->contract()]);
    }

    /** Ejecuta una acción del servicio traduciendo sus errores de negocio a mensajes del panel. */
    protected function act(callable $fn, string $ok)
    {
        try {
            $fn(app(AnticreticoService::class), $this->contract());
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success($ok);

        return \Redirect::refresh();
    }

    public function update_onActivateForm() { return $this->popup('popup_activate'); }
    public function update_onRenewForm() { return $this->popup('popup_renew'); }
    public function update_onDisputeForm() { return $this->popup('popup_dispute'); }
    public function update_onResolveForm() { return $this->popup('popup_resolve'); }
    public function update_onCancelForm() { return $this->popup('popup_cancel'); }

    public function update_onReserve()
    {
        return $this->act(fn ($s, $c) => $s->reserve($c), 'Contrato reservado.');
    }

    public function update_onActivate()
    {
        return $this->act(fn ($s, $c) => $s->activate($c, post('start_date') ?: null), 'Contrato vigente.');
    }

    public function update_onRenew()
    {
        $capital = post('capital');

        return $this->act(fn ($s, $c) => $s->renew($c, (int) post('months'), $capital === null || $capital === '' ? null : (float) $capital, post('note')), 'Contrato renovado.');
    }

    public function update_onFinish()
    {
        return $this->act(fn ($s, $c) => $s->finish($c), 'Contrato finalizado.');
    }

    public function update_onDispute()
    {
        return $this->act(fn ($s, $c) => $s->markDispute($c, (string) post('note')), 'Marcado en disputa.');
    }

    public function update_onResolve()
    {
        return $this->act(fn ($s, $c) => $s->resolveDispute($c, post('note')), 'Disputa resuelta.');
    }

    public function update_onCancel()
    {
        return $this->act(fn ($s, $c) => $s->cancel($c, (string) post('reason')), 'Contrato cancelado.');
    }
}
