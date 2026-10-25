<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Feedback;
use Cms\Classes\ComponentBase;

/** Opiniones aprobadas de los clientes, con el promedio. No muestra nada si la tienda no las publica. */
class ShopReviews extends ComponentBase
{
    public $reviews = [];
    public float $average = 0;
    public int $total = 0;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Shop Opiniones',
            'description' => 'Muestra las opiniones públicas (aprobadas) de los clientes y su promedio.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'limit' => ['title' => 'Cuántas mostrar', 'type' => 'string', 'default' => '6'],
        ];
    }

    public function onRun(): void
    {
        $tenant = StorefrontContext::tenant();
        $mode = StorefrontContext::settings()?->feedback_mode;
        if (!$tenant || !in_array($mode, ['public', 'moderated'], true)) {
            return;
        }

        $base = Feedback::forTenant($tenant->id)->published();
        $this->total = (clone $base)->count();
        $this->average = $this->total ? round((float) (clone $base)->avg('rating'), 1) : 0;
        $this->reviews = $base->orderByDesc('created_at')->limit(max(1, min(24, (int) $this->property('limit'))))->get();
    }
}
