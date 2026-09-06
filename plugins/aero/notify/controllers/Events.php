<?php namespace Aero\Notify\Controllers;

use Aero\Notify\Models\Event;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/**
 * Catálogo de eventos notificables.
 *
 * El catálogo es de la plataforma: solo el superadmin lo edita. Un
 * administrador de tenant entra en modo lectura, para saber qué eventos
 * existen y qué variables trae cada uno antes de escribir sus plantillas.
 */
class Events extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    // Cualquiera de los dos permisos abre la pantalla; lo que se puede hacer
    // dentro lo decide canManage().
    public $requiredPermissions = ['aero.notify.view_events', 'aero.notify.manage_events'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Aero.Notify', 'notify', 'notify-events');

        $this->vars['canManage'] = $this->canManage();
    }

    /**
     * Ojo: hasAccess() y no hasPermission(). Solo el primero honra el flag
     * is_superuser de October; con hasPermission() un superusuario sin rol
     * asignado se queda fuera de su propio catálogo.
     */
    protected function canManage(): bool
    {
        $user = BackendAuth::getUser();

        return $user && $user->hasAccess(['aero.notify.manage_events']);
    }

    /**
     * En modo lectura los campos se muestran deshabilitados en vez de
     * ocultarse: el valor configurado es justamente lo que el tenant necesita
     * consultar.
     */
    public function formExtendFields($form): void
    {
        if ($this->canManage()) {
            return;
        }

        foreach ($form->getFields() as $field) {
            $field->disabled = true;
        }
    }

    public function formBeforeSave($model): void
    {
        $this->assertCanManage();
    }

    public function formBeforeDelete($model): void
    {
        $this->assertCanManage();

        if ($model->is_system) {
            throw new \ApplicationException(
                'Este evento es del sistema y no se puede eliminar. Si no querés que notifique, desactivalo.'
            );
        }
    }

    protected function assertCanManage(): void
    {
        if (!$this->canManage()) {
            throw new \ApplicationException('No tenés permiso para modificar el catálogo de eventos.');
        }
    }

    /**
     * Baja múltiple desde el listado. El listado tiene checkboxes, así que el
     * botón tiene que existir de verdad y respetar los permisos.
     */
    public function index_onDelete()
    {
        $this->assertCanManage();

        $checked = (array) post('checked');

        if (!$checked) {
            Flash::error('No seleccionaste ningún evento.');

            return $this->listRefresh();
        }

        $events = Event::whereIn('id', $checked)->get();
        $deleted = 0;
        $skipped = 0;

        foreach ($events as $event) {
            if ($event->is_system) {
                $skipped++;
                continue;
            }

            $event->delete();
            $deleted++;
        }

        if ($deleted) {
            Flash::success("{$deleted} evento(s) eliminado(s).");
        }

        if ($skipped) {
            Flash::warning("{$skipped} evento(s) del sistema se omitieron.");
        }

        return $this->listRefresh();
    }
}
