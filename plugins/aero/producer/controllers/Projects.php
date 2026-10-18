<?php namespace Aero\Producer\Controllers;

use Aero\Producer\Classes\Importer;
use Aero\Producer\Classes\YoutubeBridge;
use Aero\Producer\Models\Project;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

class Projects extends Controller
{
    use \Aero\Producer\Classes\ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.producer.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Producer', 'producer', 'projects');
    }

    public function index()
    {
        $this->vars['available'] = Importer::available();
        $this->asExtension('ListController')->index();
    }

    /** Trae los videos de video-cli que aún no son proyecto. */
    public function index_onImport()
    {
        $count = 0;
        foreach (Importer::available() as $slug) {
            Importer::import($slug) && $count++;
        }

        $count ? Flash::success("{$count} proyecto(s) importados.") : Flash::warning('No hay videos nuevos para importar.');

        return \Backend::redirect('aero/producer/projects');
    }

    /** Relee la carpeta y Medios (guion, datos que falten y archivos finales) sin pisar lo editado. */
    public function update_onSync($recordId = null)
    {
        $project = Project::findOrFail($recordId);
        $project->slug && Importer::import($project->slug);
        Flash::success('Proyecto actualizado desde la carpeta y Medios.');

        return \Backend::redirect('aero/producer/projects/update/' . $project->id);
    }

    /** Deja borradores privados en YouTube → Subidas; no sube nada. */
    public function update_onSendToYoutube($recordId = null)
    {
        $project = Project::findOrFail($recordId);

        if (!YoutubeBridge::available()) {
            Flash::error('El plugin YouTube no está instalado.');

            return;
        }
        if ($problems = $project->metadataProblems()) {
            Flash::error(implode(' ', $problems));

            return;
        }

        $result = YoutubeBridge::send($project, BackendAuth::getUser()?->id);
        Flash::success("{$result['created']} borrador(es) creados en YouTube → Subidas ({$result['skipped']} ya estaban).");

        return \Backend::redirect('aero/youtube/uploads');
    }
}
