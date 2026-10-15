<?php namespace Aero\School\Controllers;

use Aero\School\Classes\Reports;
use Aero\School\Classes\TenantScreen;
use Aero\School\Models;
use Backend\Classes\Controller;
use BackendMenu;

class Dashboard extends Controller
{
    use TenantScreen;

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'dashboard');
        $this->pageTitle = 'Panel del colegio';
    }

    public function index()
    {
        $t = $this->tenantId();
        $reports = new Reports($t);
        $settings = Models\Settings::where('tenant_id', $t)->first();

        $this->vars['kpis'] = $reports->kpis();
        $this->vars['aging'] = $reports->aging();
        $this->vars['today'] = Models\Attendance::where('tenant_id', $t)->whereDate('date', today())
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $this->vars['announcements'] = Models\Announcement::where('tenant_id', $t)->latest('id')->limit(3)->get();
        $this->vars['steps'] = [
            ['Configurar los datos del colegio (nombre, SIE, director)', $settings && $settings->school_name, 'settings'],
            ['Crear la gestión vigente', Models\Year::where('tenant_id', $t)->exists(), 'years/create'],
            ['Crear los cursos', Models\Course::where('tenant_id', $t)->exists(), 'courses/create'],
            ['Cargar las áreas del currículo en cada curso', Models\Subject::where('tenant_id', $t)->exists(), 'courses'],
            ['Definir precios de matrícula y pensión por nivel', Models\FeePlan::where('tenant_id', $t)->exists(), 'prices'],
            ['Registrar estudiantes con su apoderado', Models\Student::where('tenant_id', $t)->exists(), 'students/create'],
            ['Generar las cuotas (se hace al guardar los precios)', Models\Charge::where('tenant_id', $t)->exists(), 'prices'],
            ['Conectar WhatsApp (Hello) para los avisos', \Aero\School\Classes\Notifier::available(), null],
        ];
    }
}
