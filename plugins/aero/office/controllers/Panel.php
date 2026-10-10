<?php namespace Aero\Office\Controllers;

use Aero\Office\Classes\CurrentTenant;
use Aero\Office\Models\Booking;
use Aero\Office\Models\Branch;
use Aero\Office\Models\OfficeSettings;
use Aero\Office\Models\Service;
use Aero\Office\Models\Worker;
use Backend\Classes\Controller;
use BackendMenu;

/** Resumen del día, solicitudes pendientes y guía de configuración inicial. */
class Panel extends Controller
{
    public $requiredPermissions = ['aero.office.use', 'aero.office.reception', 'aero.office.professional', 'aero.office.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Office', 'office', 'panel');
        $this->pageTitle = 'Resumen';
    }

    public function index(): void
    {
        $tenantId = CurrentTenant::id();
        $today = now()->startOfDay();

        $base = fn () => Booking::visibleToUser();
        $this->vars['today'] = $base()->whereBetween('starts_at', [$today, $today->copy()->endOfDay()])
            ->whereIn('status', ['pending', 'confirmed', 'in_service', 'completed'])->with(['customer', 'worker'])->orderBy('starts_at')->get();
        $this->vars['pending'] = $base()->where('status', 'pending')->where('starts_at', '>=', now())->with(['customer', 'worker'])
            ->orderBy('starts_at')->limit(15)->get();
        $this->vars['counts'] = [
            'today'     => $this->vars['today']->count(),
            'pending'   => $base()->where('status', 'pending')->count(),
            'week'      => $base()->whereBetween('starts_at', [$today, $today->copy()->addDays(7)->endOfDay()])->whereIn('status', ['pending', 'confirmed'])->count(),
            'no_shows'  => $base()->where('status', 'no_show')->where('starts_at', '>=', now()->subDays(30))->count(),
        ];

        $user = \BackendAuth::getUser();
        $canManage = CurrentTenant::isAdmin() || $user->hasAccess('aero.office.use') || $user->hasAccess('aero.office.settings');
        $this->vars['steps'] = ($canManage && $tenantId) ? $this->steps($tenantId) : [];
        $this->vars['publicUrl'] = null;
        if ($tenantId && class_exists(\Aero\Sites\Models\Tenant::class) && ($t = \Aero\Sites\Models\Tenant::find($tenantId))
            && OfficeSettings::isPublic($tenantId)) {
            $this->vars['publicUrl'] = request()->getScheme() . '://' . $t->primary_domain . '/reservas';
        }
    }

    /** Los 6 pasos de la configuración inicial, con su estado real. */
    protected function steps(int $tenantId): array
    {
        $s = OfficeSettings::where('tenant_id', $tenantId)->first();
        $branches = Branch::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $hasHours = $branches->contains(fn ($b) => !empty($b->hours));

        return [
            ['n' => 1, 'label' => 'Completa la información del negocio', 'done' => (bool) ($s && $s->business_name), 'url' => 'aero/office/settings'],
            ['n' => 2, 'label' => 'Registra una sucursal', 'done' => $branches->isNotEmpty(), 'url' => 'aero/office/branches/create'],
            ['n' => 3, 'label' => 'Crea tus servicios', 'done' => Service::where('tenant_id', $tenantId)->where('is_active', true)->exists(), 'url' => 'aero/office/services/create'],
            ['n' => 4, 'label' => 'Registra a tus profesionales', 'done' => Worker::where('tenant_id', $tenantId)->where('is_active', true)->exists(), 'url' => 'aero/office/workers/create'],
            ['n' => 5, 'label' => 'Configura horarios y disponibilidad', 'done' => $hasHours, 'url' => 'aero/office/branches'],
            ['n' => 6, 'label' => 'Publica tu enlace de reservas', 'done' => OfficeSettings::isPublic($tenantId), 'url' => 'aero/office/settings'],
        ];
    }
}
