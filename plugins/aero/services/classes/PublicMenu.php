<?php namespace Aero\Services\Classes;

use Aero\Services\Models\Service;

/**
 * Menú público de servicios agrupado por categoría. Una sola fuente para el
 * megamenú del theme master (componente Services, mode=menu) y para el
 * endpoint JSON que consumen otros sitios (GET /api/v1/services/menu).
 */
class PublicMenu
{
    /**
     * @param string|null $base Origen de las URLs (https://market.com.bo). null = url() del sitio actual.
     * @return array<int, array{name:string, slug:?string, url:?string, description:?string, icon:?string, order:int, items:array}>
     */
    public static function groups(?string $base = null): array
    {
        $link = fn (string $path) => $base === null ? url($path) : rtrim($base, '/') . '/' . ltrim($path, '/');

        $services = Service::active()
            ->with(['categories' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        // Un servicio aparece en cada una de sus categorías (o en «Otros servicios» si no tiene).
        $buckets = [];
        foreach ($services as $s) {
            $cats = $s->categories->isNotEmpty() ? $s->categories : collect([null]);
            foreach ($cats as $c) {
                $buckets[$c?->id ?? 0]['category'] = $c;
                $buckets[$c?->id ?? 0]['items'][] = $s;
            }
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            $category = $bucket['category'];
            $groups[] = [
                'name'        => $category?->name ?: 'Otros servicios',
                'slug'        => $category?->slug,
                'url'         => $category ? $link('plugins/' . $category->slug) : null,
                'description' => $category?->description,
                // Las categorías pueden traer clases del backend (icon-server): solo se expone un nombre de Lucide.
                'icon'        => $category && $category->icon && strpos($category->icon, 'icon-') !== 0 ? $category->icon : null,
                'order'       => $category?->sort_order ?? PHP_INT_MAX,
                'items'       => collect($bucket['items'])->map(fn ($s) => [
                    'name'    => $s->name,
                    'summary' => $s->summary,
                    'icon'    => $s->icon ?: null,
                    'slug'    => $s->slug,
                    'url'     => $link('plugin/' . $s->slug),
                ])->all(),
            ];
        }

        usort($groups, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $groups;
    }
}
