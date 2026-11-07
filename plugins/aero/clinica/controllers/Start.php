<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Models\Analyte;
use Aero\Clinica\Models\Charge;
use Aero\Clinica\Models\Consultation;
use Aero\Clinica\Models\DiagnosisCatalog;
use Aero\Clinica\Models\DocumentTemplate;
use Aero\Clinica\Models\ExamCatalog;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Professional;
use Aero\Clinica\Models\Range;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** Lista de primeros pasos: lo mínimo para empezar y lo opcional, con su estado. Solo lee. */
class Start extends Controller
{
    public $requiredPermissions = ['aero.clinica.use', 'aero.clinica.reception', 'aero.clinica.professional', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'start');
    }

    public function index(): void
    {
        $this->pageTitle = 'Primeros pasos';
        $tenant = CurrentTenant::id();
        $this->vars['tenant'] = $tenant;
        $this->vars['required'] = [];
        $this->vars['optional'] = [];
        if (!$tenant) {
            return;
        }

        $user = BackendAuth::getUser();
        $me = $user ? Professional::where('tenant_id', $tenant)->where('user_id', $user->id)->first() : null;
        $url = fn (string $p) => \Backend::url('aero/clinica/' . $p);
        $count = fn ($model) => $model::where('tenant_id', $tenant)->count();

        $this->vars['required'] = [
            ['done' => (bool) ($me && $me->license && $me->signature_image), 'title' => 'Complete su firma', 'why' => 'Matrícula, especialidad y las imágenes de firma y sello salen en cada nota, receta y documento.', 'url' => $url('signature'), 'cta' => 'Ir a Mi firma'],
            ['done' => $count(DocumentTemplate::class) > 0, 'title' => 'Cargue los formatos de documentos', 'why' => 'Certificado, constancia, informe, consentimiento y derivación, listos para editar. Se usan desde la ficha del paciente.', 'url' => $url('documenttemplates'), 'cta' => 'Cargar formatos médicos iniciales'],
            ['done' => $count(Patient::class) > 0, 'title' => 'Registre su primer paciente', 'why' => 'Todo cuelga de la ficha: consultas, laboratorio, vacunas y documentos.', 'url' => $url('patients/create'), 'cta' => 'Nuevo paciente'],
            ['done' => Consultation::where('tenant_id', $tenant)->where('status', 'signed')->exists(), 'title' => 'Escriba y firme una consulta', 'why' => 'Se inicia desde la agenda o desde la ficha del paciente. Al firmar, la nota queda inmutable.', 'url' => $url('patients'), 'cta' => 'Abrir un paciente'],
        ];

        $this->vars['optional'] = [
            ['done' => $count(Analyte::class) > 0, 'title' => 'Analitos', 'why' => 'Lo que mide su laboratorio. Vienen 6 por defecto; agregue los suyos.', 'url' => $url('analytes')],
            ['done' => $count(ExamCatalog::class) > 0, 'title' => 'Exámenes frecuentes', 'why' => 'Atajo para pedir exámenes. Puede escribirlos a mano sin cargarlos.', 'url' => $url('examcatalogs')],
            ['done' => $count(DiagnosisCatalog::class) > 0, 'title' => 'Diagnósticos favoritos', 'why' => 'Salen primero en el buscador. CIE-10 ya está disponible.', 'url' => $url('diagnosiscatalogs')],
            ['done' => $count(Range::class) > 0, 'title' => 'Rangos alto/bajo', 'why' => 'Solo si un médico aprueba los valores. En laboratorio use la referencia del propio laboratorio.', 'url' => $url('ranges')],
            ['done' => $count(Charge::class) > 0, 'title' => 'Cobros', 'why' => 'Registro aparte de lo cobrado por la atención (efectivo, transferencia o QR).', 'url' => $url('charges')],
        ];
    }
}
