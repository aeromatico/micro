<?php namespace Aero\Church\Components;

use Aero\Church\Classes\HomeContent;
use Aero\Church\Classes\PortalAccess;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

/** Portada de la iglesia: el tema la muestra mientras el negocio no publique una portada propia. */
class ChurchHome extends ComponentBase
{
    public bool $active = false;
    public ?array $data = null;
    public string $churchName = '';

    public function componentDetails(): array
    {
        return ['name' => 'Iglesia (portada)', 'description' => 'Accesos rápidos, próximos eventos, predicaciones, sedes y noticias.'];
    }

    public function onRun()
    {
        $tenant = class_exists(Tenant::class) ? Tenant::resolveFromDomain(request()->getHost()) : null;
        if (!$tenant) {
            return;
        }
        $this->data = HomeContent::forTenant($tenant->id, PortalAccess::isMember(\Auth::user(), $tenant->id));
        $this->active = $this->data !== null;
        $this->churchName = (string) $tenant->name;
        $this->page['churchHome'] = $this;
    }
}
