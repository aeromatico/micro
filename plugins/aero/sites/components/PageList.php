<?php namespace Aero\Sites\Components;

use Aero\Sites\Models\Page;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

class PageList extends ComponentBase
{
    public array $pages = [];

    public function componentDetails(): array
    {
        return [
            'name'        => 'Sites Lista de Páginas',
            'description' => 'Muestra la lista de páginas publicadas del tenant.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'layout' => [
                'title'       => 'Filtrar por layout',
                'description' => 'Deja vacío para mostrar todas las páginas.',
                'type'        => 'string',
                'default'     => '',
            ],
            'excludeHome' => [
                'title'       => 'Excluir homepage',
                'description' => 'No mostrar la página con slug vacío.',
                'type'        => 'checkbox',
                'default'     => true,
            ],
        ];
    }

    public function onRun(): void
    {
        $host = request()->getHost();
        $tenant = Tenant::resolveFromDomain($host);
        if (!$tenant) return;

        $query = Page::forTenant($tenant->id)->published()->orderBy('sort_order');

        if ($layout = $this->property('layout')) {
            $query->where('layout', $layout);
        }

        if ($this->property('excludeHome')) {
            $query->where('slug', '!=', '');
        }

        $this->pages = $query->get()->toArray();
    }
}
