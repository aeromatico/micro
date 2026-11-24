<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\ModuleCatalog;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * «Mis módulos»: el tenant enciende o apaga cada módulo de su plan, y la
 * primera vez que entra elige los suyos (welcome). Solo actúa sobre el
 * tenant resuelto; sin tenant no muestra ni cambia nada (falla cerrado).
 */
class MyModules extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.sites.mymodules'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'mis-modulos');
        $this->addCss('/plugins/aero/sites/assets/css/mymodules.css', ['v' => '2']);
        $this->addJs('/plugins/aero/sites/assets/js/mymodules.js', ['v' => '1']);
    }

    public function index()
    {
        $this->pageTitle = 'Mis módulos';
        $tenant = $this->getCurrentTenant();
        $this->vars['tenant'] = $tenant;
        $this->vars['groups'] = $tenant ? ModuleCatalog::forTenant($tenant) : [];
        $off = (string) request()->query('off', '');
        $this->vars['off'] = ModuleCatalog::isToggleable($off) ? ModuleCatalog::displayName($off) : null;
        $this->vars['offCode'] = $this->vars['off'] ? $off : null;
    }

    public function welcome()
    {
        $this->pageTitle = 'Elige tus módulos';
        $tenant = $this->getCurrentTenant();
        $this->vars['tenant'] = $tenant;
        $this->vars['groups'] = $tenant ? ModuleCatalog::forTenant($tenant, ModuleCatalog::defaultsFor($tenant)) : [];
        $this->vars['niche'] = $tenant ? ($tenant->getNicheTypeOptions()[$tenant->niche_type] ?? null) : null;
        if ($this->vars['niche'] === 'Generic') {
            $this->vars['niche'] = null;
        }
    }

    public function onToggle(): array
    {
        $tenant = $this->tenantOrFail();
        $code = (string) post('code');
        $enable = filter_var(post('enabled'), FILTER_VALIDATE_BOOLEAN);

        $changed = ModuleCatalog::toggle($tenant, $code, $enable);
        $names = array_map([ModuleCatalog::class, 'displayName'], $changed);

        if ($enable) {
            $msg = $names ? implode(', ', $names) . (count($names) > 1 ? ' encendidos.' : ' encendido.') : 'Ya estaba encendido.';
        } else {
            $msg = $names ? implode(', ', $names) . (count($names) > 1 ? ' apagados.' : ' apagado.') . ' Tus datos se guardan.' : 'Ya estaba apagado.';
        }

        return ['states' => $this->states($tenant), 'message' => $msg];
    }

    /** Bienvenida: guarda lo elegido (o la preselección si «Saltar»). */
    public function onFinishWelcome()
    {
        $tenant = $this->tenantOrFail();
        $codes = post('skip') ? ModuleCatalog::defaultsFor($tenant) : (array) post('codes', []);

        ModuleCatalog::applySelection($tenant, array_map('strval', $codes));

        return Backend::redirect('backend');
    }

    protected function states($tenant): array
    {
        $out = [];
        foreach (ModuleCatalog::codes() as $code) {
            $out[$code] = $tenant->pluginEnabled($code);
        }

        return $out;
    }

    protected function tenantOrFail()
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            throw new \ApplicationException('No se encontró tu espacio.');
        }

        return $tenant;
    }
}
