<?php namespace Aero\Services\Components;

use Aero\Services\Models\Category;
use Aero\Services\Models\Collection;
use Aero\Services\Models\Service;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\Response;

/**
 * Servicios en el sitio público.
 *   mode = "menu"       → $groups: servicios del megamenú agrupados por categoría.
 *   mode = "detail"     → $service: el servicio público de :slug (404 si no existe o es borrador).
 *   mode = "collection" → $collection: la colección de :slug con sus servicios (404 si no existe).
 */
class Services extends ComponentBase
{
    /** @var array<int, array{name:string, items:array}> */
    public array $groups = [];

    public ?Service $service = null;

    public ?Collection $collection = null;

    public function componentDetails(): array
    {
        return ['name' => 'Servicios', 'description' => 'Megamenú y página pública de un servicio.'];
    }

    public function defineProperties(): array
    {
        return [
            'mode' => ['title' => 'Modo', 'type' => 'dropdown', 'default' => 'menu',
                'options' => ['menu' => 'Megamenú', 'detail' => 'Detalle', 'collection' => 'Colección']],
            'slug' => ['title' => 'Slug', 'type' => 'string', 'default' => '{{ :slug }}'],
        ];
    }

    public function onRun()
    {
        if ($this->property('mode') === 'detail') {
            $this->service = Service::active()->where('slug', $this->property('slug'))->first();

            if (!$this->service) {
                return Response::make($this->controller->run('404'), 404);
            }

            $this->page['service'] = $this->service;
            $this->page->title = $this->service->name . ' — Market';
            $this->page->description = $this->service->summary;

            return null;
        }

        if ($this->property('mode') === 'collection') {
            $this->collection = Collection::with(['services' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
                ->where('slug', $this->property('slug'))->first();

            if (!$this->collection) {
                return Response::make($this->controller->run('404'), 404);
            }

            $this->page['collection'] = $this->collection;
            $this->page->title = $this->collection->name . ' — Market';
            $this->page->description = $this->collection->summary;

            return null;
        }

        $this->groups = $this->menuGroups();
    }

    protected function menuGroups(): array
    {
        $services = Service::inMegamenu()->with('category')->orderBy('sort_order')->orderBy('name')->get();

        $groups = [];
        foreach ($services->groupBy(fn ($s) => $s->category_id ?: 0) as $categoryId => $items) {
            $category = $categoryId ? $items->first()->category : null;
            $groups[] = [
                'name'  => $category?->name ?: 'Otros servicios',
                'order' => $category?->sort_order ?? PHP_INT_MAX,
                'items' => $items->map(fn ($s) => ['name' => $s->name, 'summary' => $s->summary, 'url' => url('servicio/' . $s->slug)])->all(),
            ];
        }
        usort($groups, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $groups;
    }
}
