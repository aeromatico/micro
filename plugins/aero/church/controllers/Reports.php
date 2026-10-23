<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\Audit;
use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\CurrentTenant;
use Aero\Church\Classes\ReportCatalog;
use Aero\Church\Classes\ReportExporter;
use Aero\Church\Classes\ReportService;
use Aero\Church\Models\Branch;
use Aero\Church\Models\Member;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** Reportes con filtros y exportación a Excel/PDF. Cada reporte comprueba su propio permiso. */
class Reports extends Controller
{
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.treasury', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'reports');
        $this->pageTitle = 'Reportes';
    }

    protected function filters(): array
    {
        return [
            'report' => (string) get('report'), 'from' => (string) get('from'), 'to' => (string) get('to'),
            'status' => (string) get('status'), 'branch' => Branch::visible()->whereKey((int) get('branch'))->value('id'),
        ];
    }

    protected function service(): ReportService
    {
        return new ReportService(CurrentTenant::isAdmin() ? null : CurrentTenant::id());
    }

    /** Falla cerrado: sin tenant resoluble (y sin ser superadmin) no hay reportes. */
    protected function guardTenant(): void
    {
        if (!CurrentTenant::isAdmin() && !CurrentTenant::id()) {
            throw new \ApplicationException('No se pudo determinar su iglesia.');
        }
    }

    public function index()
    {
        $this->guardTenant();
        $allowed = ReportCatalog::allowed(BackendAuth::getUser());
        $f = $this->filters();
        $key = isset($allowed[$f['report']]) ? $f['report'] : (array_key_first($allowed) ?: '');

        $this->vars['allowed'] = $allowed;
        $this->vars['f'] = $f + ['key' => $key];
        $this->vars['f']['report'] = $key;
        $this->vars['branches'] = Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
        $this->vars['statuses'] = Member::STATUSES;
        $this->vars['report'] = $key ? ReportCatalog::run($key, $this->service(), $f) : null;
    }

    public function export($format = 'xlsx')
    {
        $this->guardTenant();
        $allowed = ReportCatalog::allowed(BackendAuth::getUser());
        $f = $this->filters();
        if (!isset($allowed[$f['report']]) || !in_array($format, ['xlsx', 'pdf'], true)) {
            abort(403);
        }

        $report = ReportCatalog::run($f['report'], $this->service(), $f);
        $ex = new ReportExporter();
        $church = class_exists(\Aero\Sites\Models\Tenant::class) && CurrentTenant::id() ? (string) \Aero\Sites\Models\Tenant::find(CurrentTenant::id())?->name : '';

        Audit::record('exported', 'report', null, "Reporte «{$report['title']}» exportado a " . strtoupper($format) . ' (' . count($report['rows']) . ' filas)');

        if ($format === 'pdf') {
            $bytes = $ex->pdf($report, $church);
            if ($bytes === null) {
                return response($ex->html($report, $church), 200, ['Content-Type' => 'text/html; charset=utf-8']);
            }

            return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $ex->filename($report, 'pdf') . '"']);
        }

        return response($ex->xlsx($report), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $ex->filename($report, 'xlsx') . '"',
        ]);
    }
}
