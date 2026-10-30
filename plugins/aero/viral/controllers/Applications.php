<?php namespace Aero\Viral\Controllers;

use Aero\Viral\Models\Application;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/** Revisión de postulaciones (solo plataforma). */
class Applications extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.viral.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Viral', 'viral', 'applications');
    }

    public function update_onInReview($id)
    {
        $this->findApp($id)->markInReview(BackendAuth::user()?->id);
        Flash::success('Marcada en revisión.');
        return \Backend::redirect('aero/viral/applications/update/' . (int) $id);
    }

    public function update_onApprove($id)
    {
        try {
            $this->findApp($id)->approve(BackendAuth::user()?->id, post('review_notes'));
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            return;
        }
        Flash::success('Postulación aprobada.');
        return \Backend::redirect('aero/viral/applications');
    }

    public function update_onReject($id)
    {
        $this->findApp($id)->reject(BackendAuth::user()?->id, post('review_notes'));
        Flash::success('Postulación rechazada.');
        return \Backend::redirect('aero/viral/applications');
    }

    public function update_onRequestChanges($id)
    {
        $notes = trim((string) post('review_notes'));
        if ($notes === '') {
            Flash::error('Escribe qué debe corregir la persona.');
            return;
        }
        $this->findApp($id)->requestChanges(BackendAuth::user()?->id, $notes);
        Flash::success('Se pidieron cambios.');
        return \Backend::redirect('aero/viral/applications');
    }

    protected function findApp($id): Application
    {
        return Application::findOrFail((int) $id);
    }

    /** Foto de perfil privada: solo para quien revisa. */
    public function photo($id = null)
    {
        $app = \Aero\Viral\Models\Application::find((int) $id);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (!$app || !$app->photo_path || !$disk->exists($app->photo_path)) {
            abort(404);
        }

        return response($disk->get($app->photo_path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
