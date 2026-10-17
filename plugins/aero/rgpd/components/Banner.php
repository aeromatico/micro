<?php namespace Aero\Rgpd\Components;

use Aero\Rgpd\Models\Config;
use Cms\Classes\ComponentBase;

/** {% component 'rgpdBanner' %} en el layout. Sin configuración activa no imprime nada. */
class Banner extends ComponentBase
{
    public ?Config $rgpd = null;
    public string $jsUrl = '';

    public function componentDetails(): array
    {
        return ['name' => 'Aviso de cookies (RGPD)', 'description' => 'Ya está en los layouts de Market y de los sitios; no necesita añadirse a mano. Se configura en el menú Cookies (RGPD).'];
    }

    public function onRun(): void
    {
        $config = Config::forScope(Config::tenantIdForHost(request()->getHost()));
        $this->rgpd = ($config && $config->is_enabled) ? $config : null;

        $file = plugins_path('aero/rgpd/assets/js/rgpd.js');
        $this->jsUrl = '/plugins/aero/rgpd/assets/js/rgpd.js?v=' . (is_file($file) ? filemtime($file) : 1);
    }
}
