<?php namespace Aero\WpFlash\Controllers;

use ApplicationException;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Aero\WpFlash\Classes\Provisioner;
use Aero\WpFlash\Models\SiteInstance;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/**
 * Pantalla del tenant para elegir el motor de su sitio (Plataforma Aero vs
 * WordPress Flash). Mismo patrón que SiteSettings.php de Aero.Sites: un solo
 * index() + handlers AJAX, sin FormController (no es un CRUD, es un toggle
 * con un flujo de provisioning detrás).
 */
class SiteEngine extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.wpflash.manage_sites'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.WpFlash', 'wpflash', 'siteengine');
    }

    public function index()
    {
        $this->pageTitle = 'Motor del sitio';
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            $this->vars['noTenant'] = true;
            return;
        }

        $this->vars['tenant']       = $tenant;
        $this->vars['site']         = SiteInstance::where('tenant_id', $tenant->id)->first();
        $this->vars['planAllows']   = $tenant->plan?->allowsPlugin('Aero.WpFlash') ?? true;
    }

    public function onActivate()
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant->plan?->allowsPlugin('Aero.WpFlash') && $tenant->plan !== null) {
            throw new ApplicationException('Tu plan actual no incluye WordPress Flash.');
        }

        $site = app(Provisioner::class)->provision($tenant);

        Flash::success('WordPress Flash está listo. Guarda las credenciales que se muestran abajo: no se volverán a mostrar en claro.');

        return ['#wpflashStatus' => $this->makePartial('_status', ['tenant' => $tenant, 'site' => $site->fresh(), 'revealed' => true])];
    }

    public function onDeactivate()
    {
        $tenant = $this->getCurrentTenant();

        app(Provisioner::class)->deprovision($tenant);

        Flash::success('Se volvió a la plataforma Aero. El childsite de WordPress no se borró, solo quedó desconectado.');

        return ['#wpflashStatus' => $this->makePartial('_status', ['tenant' => $tenant, 'site' => SiteInstance::where('tenant_id', $tenant->id)->first()])];
    }

    public function onRevealPassword()
    {
        $tenant = $this->getCurrentTenant();
        $site = SiteInstance::where('tenant_id', $tenant->id)->firstOrFail();

        return ['#wpflashStatus' => $this->makePartial('_status', ['tenant' => $tenant, 'site' => $site, 'revealed' => true])];
    }
}
