<?php namespace Aero\Sites\Components;

use Aero\Sites\Models\Page;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

class PageDetail extends ComponentBase
{
    public ?Page $page = null;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Sites Página',
            'description' => 'Muestra el contenido de una página estática por slug.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'slug' => [
                'title'       => 'Slug',
                'description' => 'Slug de la página. Usa :slug para leerlo de la URL.',
                'default'     => ':slug',
                'type'        => 'string',
            ],
        ];
    }

    public function onRun(): void
    {
        $host = request()->getHost();
        $tenant = Tenant::resolveFromDomain($host);
        if (!$tenant) return;

        $slug = $this->property('slug');

        $this->page = Page::forTenant($tenant->id)
            ->published()
            ->where('slug', $slug)
            ->first();

        if (!$this->page) {
            return $this->controller->run('404');
        }

        // Set page title and meta for SEO component
        $this->page->load(['og_image']);
    }
}
