<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\GroomingService;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\Scheduling;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\GroomingVisit;
use Aero\Medical\Models\Patient;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Peluquería y spa (solo animales). Lo opera recepción o quien atiende la peluquería, además del personal
 * clínico: no muestra nada del expediente clínico, solo la visita y sus fotos antes/después.
 */
class Groomings extends Controller
{
    use ScopesToTenant;

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.reception', 'aero.medical.use', 'aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'groomings');
    }

    public function index()
    {
        if (request()->ajax()) {
            return;
        }
        if ($redirect = $this->guardMode()) {
            return $redirect;
        }
        $this->pageTitle = 'Peluquería y spa';

        $q = trim((string) get('q'));
        $visits = $this->scopeToTenant(GroomingVisit::with('patient'))
            ->when($q !== '', fn ($w) => $w->whereHas('patient', fn ($p) => $p->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%')))
            ->orderByDesc('visited_on')->orderByDesc('id')->limit(60)->get();

        $this->vars['q'] = $q;
        $this->vars['visits'] = $visits;
        $this->vars['patients'] = $this->animals()->orderBy('name')->limit(500)->get(['id', 'name', 'species']);
        $this->vars['photoCounts'] = \Aero\Medical\Models\MedicalFile::whereIn('grooming_id', $visits->pluck('id'))->selectRaw('grooming_id, count(*) n')->groupBy('grooming_id')->pluck('n', 'grooming_id');
    }

    public function update($id = null)
    {
        if (request()->ajax()) {
            return;
        }
        if ($redirect = $this->guardMode()) {
            return $redirect;
        }
        $this->pageTitle = 'Visita de peluquería y spa';
        $this->prepare($this->visitOrFail($id));
    }

    protected function prepare(GroomingVisit $v): void
    {
        $this->vars['v'] = $v;
        $this->vars['before'] = $v->photosOf('before');
        $this->vars['after'] = $v->photosOf('after');
        $this->vars['office'] = Scheduling::available() && $v->patient->customer_id;
        $this->vars['opt'] = Scheduling::options((int) $v->tenant_id);
    }

    public function index_onCreate()
    {
        $p = $this->animals()->find((int) post('patient_id'));
        if (!$p) {
            throw new \ApplicationException('Elija un paciente (animal).');
        }

        $v = $this->guard(fn () => app(GroomingService::class)->create($p, [
            'service' => post('service'), 'visited_on' => post('visited_on'), 'notes' => post('notes'), 'next_visit_on' => post('next_visit_on'),
        ], BackendAuth::getUser()));

        return \Backend::redirect('aero/medical/groomings/update/' . $v->id);
    }

    public function update_onSave($id = null)
    {
        $v = $this->visitOrFail($id);
        $this->guard(fn () => app(GroomingService::class)->update($v, [
            'service' => post('service'), 'visited_on' => post('visited_on'), 'notes' => post('notes'), 'next_visit_on' => post('next_visit_on'),
        ]));
        \Flash::success('Visita guardada.');
    }

    public function update_onUploadPhoto($id = null)
    {
        $v = $this->visitOrFail($id);
        $file = \Request::file('photo_file');
        if (!$file instanceof \Illuminate\Http\UploadedFile) {
            throw new \ApplicationException('Elija una foto.');
        }
        $this->guard(fn () => app(GroomingService::class)->addPhoto($v, (string) post('side'), $file, BackendAuth::getUser()));

        return \Backend::redirect('aero/medical/groomings/update/' . $v->id);
    }

    public function update_onRemovePhoto($id = null)
    {
        $v = $this->visitOrFail($id);
        $this->guard(fn () => app(GroomingService::class)->removePhoto($v, (int) post('file_id')));

        return \Backend::redirect('aero/medical/groomings/update/' . $v->id);
    }

    public function update_onBookNext($id = null)
    {
        $v = $this->visitOrFail($id);
        $b = $this->guard(fn () => app(GroomingService::class)->bookNext($v, [
            'branch_id' => post('branch_id'), 'service_id' => post('service_id'), 'worker_id' => post('worker_id'), 'starts_at' => post('starts_at'), 'reason' => post('reason'),
        ], BackendAuth::getUser()));
        \Flash::success('Próxima cita agendada para el ' . $b->starts_at->format('d/m/Y H:i') . ' (reserva ' . $b->code . ').');

        return \Backend::redirect('aero/medical/groomings/update/' . $v->id);
    }

    public function update_onDelete($id = null)
    {
        $v = $this->visitOrFail($id);
        $this->guard(fn () => app(GroomingService::class)->delete($v));
        \Flash::success('Visita eliminada.');

        return \Backend::redirect('aero/medical/groomings');
    }

    protected function animals()
    {
        return $this->scopeToTenant(Patient::where('kind', Mode::ANIMAL)->where('is_active', true));
    }

    protected function visitOrFail($id): GroomingVisit
    {
        return $this->scopeToTenant(GroomingVisit::with('patient')->whereKey((int) $id))->firstOrFail();
    }

    /** Sin modo animal el módulo no existe para ese negocio. */
    protected function guardMode()
    {
        if (!CurrentTenant::isAdmin() && Mode::forTenant(CurrentTenant::id()) !== Mode::ANIMAL) {
            \Flash::info('Peluquería y spa es solo para negocios en modo animal.');

            return \Backend::redirect('aero/medical/patients');
        }

        return null;
    }

    protected function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }
}
