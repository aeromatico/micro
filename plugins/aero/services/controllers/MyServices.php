<?php namespace Aero\Services\Controllers;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Classes\Money;
use Aero\Credits\Models\CreditType;
use Aero\Services\Classes\Checkout;
use Aero\Services\Models\Service;
use Aero\Services\Models\ServicePurchase;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * "App Store": catálogo de planes que el tenant puede comprar con sus
 * monedas o su saldo en Bs (mismas cuentas de Aero.Credits que Wallet). Sin
 * permiso requerido, como Wallet: cualquier usuario del panel con un tenant
 * resoluble ve y compra para SU tenant, nunca para otro. Se accede desde el
 * ícono del menú inferior (ver Aero.Credits\Plugin::bootNavbarWidget()), no
 * desde el menú lateral: no tiene entrada en registerNavigation().
 */
class MyServices extends Controller
{
    public $requiredPermissions = [];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Services', 'app-store', 'app-store');
        $this->pageTitle = 'App Store';
    }

    public function index()
    {
        $tenantId = Credits::resolveCurrentTenantId();

        $this->vars['tenantId'] = $tenantId;
        $this->vars['services'] = collect();
        $this->vars['purchases'] = collect();
        $this->vars['balances'] = [];
        $this->vars['walletUnits'] = 0;

        if (!$tenantId) {
            return;
        }

        $this->vars['services'] = Service::active()->with('categories')->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($s) => collect((array) $s->plans)->isNotEmpty());

        $this->vars['purchases'] = ServicePurchase::forTenant($tenantId)->with('service')
            ->orderByDesc('id')->limit(50)->get();

        $this->vars['balances'] = CreditType::active()->get()
            ->mapWithKeys(fn ($t) => [$t->code => ['label' => $t->label, 'color' => $t->color, 'balance' => Credits::balance($tenantId, $t->code)]])->all();

        $this->vars['walletUnits'] = Credits::walletUnits($tenantId);
        $this->vars['walletBs'] = Money::format($this->vars['walletUnits']);
    }

    /**
     * Pantalla de compra de un servicio: destino del botón «Comprar» de /store.
     * Sin sesión, el panel pide iniciarla y vuelve aquí (url.intended).
     */
    public function buy($slug = null)
    {
        $service = Service::active()->where('slug', (string) $slug)->first();

        if (!$service) {
            \Flash::error('Ese plugin ya no está disponible.');

            return \Backend::redirect('aero/services/myservices');
        }

        $this->pageTitle = 'Comprar ' . $service->name;

        $tenantId = Credits::resolveCurrentTenantId();

        $this->vars['service'] = $service;
        $this->vars['tenantId'] = $tenantId;
        $this->vars['options'] = $tenantId ? Checkout::options($service, $tenantId) : [];
        $this->vars['previous'] = $tenantId
            ? ServicePurchase::forTenant($tenantId)->where('service_id', $service->id)
                ->where('status', '!=', ServicePurchase::CANCELLED)->orderByDesc('id')->first()
            : null;
        $this->vars['wantedPlan'] = is_numeric(get('plan')) ? (int) get('plan') : null;
        $this->vars['wantedMethod'] = (string) get('method');
    }

    protected function tenantIdOrFail(): int
    {
        $tenantId = Credits::resolveCurrentTenantId();

        if (!$tenantId) {
            throw new ApplicationException('Elige primero un sitio (tenant).');
        }

        return $tenantId;
    }

    public function onBuy(): array
    {
        $tenantId = $this->tenantIdOrFail();

        $service = Service::active()->findOrFail((int) post('service_id'));
        $planIndex = (int) post('plan_index');
        $method = (string) post('method');

        try {
            $purchase = Checkout::purchase($service, $planIndex, $tenantId, $method, BackendAuth::getUser()?->id);
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException|\InvalidArgumentException|\RuntimeException $e) {
            throw new ApplicationException($e->getMessage());
        }

        $delivered = $purchase->status === ServicePurchase::FULFILLED;

        \Flash::success('Compra registrada: ' . $service->name . ' — ' . $purchase->plan_name . '. '
            . ($delivered ? 'Ya está activo.' : 'Nuestro equipo la va a entregar en breve.'));

        return ['ok' => true, 'delivered' => $delivered];
    }

    /** El tenant puede arrepentirse mientras la compra siga pendiente: reembolso inmediato. */
    public function onCancel(): array
    {
        $tenantId = $this->tenantIdOrFail();

        $purchase = ServicePurchase::forTenant($tenantId)->pending()->findOrFail((int) post('id'));

        try {
            Checkout::cancel($purchase, 'Cancelado por el cliente');
        }
        catch (\InvalidArgumentException $e) {
            throw new ApplicationException($e->getMessage());
        }

        \Flash::success('Compra cancelada y reembolsada.');

        return ['ok' => true];
    }
}
