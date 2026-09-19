<?php namespace Aero\Shop\Http\Controllers\Api;

use Aero\Shop\Classes\CatalogService;
use Aero\Shop\Models\PaymentGateway;
use Illuminate\Http\Request;

class ProductsController extends ApiController
{
    /** GET /api/v1/shop/products?q=&collection_id=&per_page= */
    public function index(Request $request)
    {
        $page = (new CatalogService())->search($this->tenantId($request), $request->query('q'), $request->query('collection_id') ? (int) $request->query('collection_id') : null, (int) $request->query('per_page', 20));

        return $this->paged($page, fn ($p) => CatalogService::present($p));
    }

    /** GET /api/v1/shop/products/{id} */
    public function show(Request $request, $id)
    {
        $p = (new CatalogService())->find($this->tenantId($request), (int) $id);

        return $p ? $this->data(CatalogService::present($p)) : $this->error('not_found', 'Producto no encontrado.', 404);
    }

    /** GET /api/v1/shop/payment-methods */
    public function paymentMethods(Request $request)
    {
        $rows = PaymentGateway::forTenant($this->tenantId($request))->active()->orderBy('sort_order')->get()
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'driver' => $g->driver, 'instructions' => $g->instructions]);

        return $this->data($rows->all());
    }
}
