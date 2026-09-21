<?php namespace Aero\Credits\Controllers;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Classes\Money;
use Aero\Credits\Classes\Recharges;
use Aero\Credits\Models\CreditPurchase;
use Aero\Credits\Models\CreditTransaction;
use Aero\Credits\Models\CreditType;
use Aero\Credits\Models\Settings;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * "Mis monedas": saldo del tenant, recarga por QR, intercambio entre monedas
 * y movimientos. Sin permiso requerido: cualquier usuario del panel con un
 * tenant resoluble ve SU billetera (nunca la de otro: el tenant sale del host
 * / sitio activo, jamás de un parámetro del request).
 */
class Wallet extends Controller
{
    public $requiredPermissions = [];

    public const KIND_LABELS = [
        'purchase' => 'Compra', 'gift' => 'Regalo', 'plan_grant' => 'Incluidas en tu plan',
        'adjust_in' => 'Ajuste (+)', 'refund' => 'Reembolso', 'exchange_in' => 'Intercambio (entrada)',
        'charge' => 'Consumo', 'exchange_out' => 'Intercambio (salida)', 'exchange_fee' => 'Comisión de intercambio',
        'adjust_out' => 'Ajuste (−)', 'expiry' => 'Vencimiento',
        'wallet_deposit' => 'Saldo en Bs (sobrante de recarga)', 'wallet_spend' => 'Compra con saldo en Bs',
    ];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'wallet', 'wallet');
        $this->pageTitle = 'Mis monedas';
    }

    public function index()
    {
        $tenantId = Credits::resolveCurrentTenantId();

        $this->vars['tenantId'] = $tenantId;
        $this->vars['types'] = [];
        $this->vars['movements'] = collect();

        if (!$tenantId) {
            return;
        }

        $balances = Credits::balances($tenantId);
        $catalog = collect(Recharges::catalog())->keyBy('code');

        $this->vars['types'] = CreditType::active()->get()->map(fn ($t) => [
            'code' => $t->code, 'label' => $t->label, 'color' => $t->color,
            'balance' => $balances[$t->code] ?? 0, 'price' => (float) $t->price_bob,
            'buyable' => $catalog->has($t->code), 'exchangeable' => (bool) $t->is_exchangeable && (float) $t->price_bob > 0,
        ])->all();

        $walletUnits = Credits::walletUnits($tenantId);
        $this->vars['walletUnits'] = $walletUnits;
        $this->vars['walletBs'] = Money::format($walletUnits);
        $this->vars['buyable'] = collect($this->vars['types'])->filter(fn ($t) => $t['buyable'])->map(fn ($t) => $t + ['priceUnits' => Money::units($t['price'])])->values()->all();
        $this->vars['amounts'] = Settings::rechargeAmounts();
        $this->vars['minAmount'] = Recharges::minimumAmount();
        $this->vars['maxAmount'] = Recharges::maximumAmount();
        $this->vars['feePercent'] = Settings::exchangeFeePercent();
        $this->vars['ttlMinutes'] = Settings::purchaseTtlMinutes();
        $this->vars['kindLabels'] = self::KIND_LABELS;
        $this->vars['movements'] = CreditTransaction::with('creditType')->where('tenant_id', $tenantId)
            ->orderByDesc('id')->limit(100)->get();
    }

    protected function tenantIdOrFail(): int
    {
        $tenantId = Credits::resolveCurrentTenantId();

        if (!$tenantId) {
            throw new ApplicationException('Elige primero un sitio (tenant).');
        }

        return $tenantId;
    }

    // ---- Recarga ----

    public function onCreatePurchase(): array
    {
        $tenantId = $this->tenantIdOrFail();
        $coin = post('coin');

        // Una recarga es de UNA sola moneda: cualquier otra forma (varias, arreglos) se rechaza.
        if ($coin !== null && $coin !== '' && !is_string($coin)) {
            throw new ApplicationException('Una recarga es de una sola moneda. Elige una.');
        }

        try {
            // Cantidad libre de UNA moneda (sin cambio) o monto cerrado de la lista.
            $purchase = post('coins') !== null && post('coins') !== ''
                ? Recharges::createExact($tenantId, (string) $coin, (int) post('coins'), BackendAuth::getUser()?->id)
                : Recharges::create($tenantId, (int) post('amount'), $coin ?: null, BackendAuth::getUser()?->id);
        }
        catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new ApplicationException($e->getMessage());
        }

        $qr = \Aero\Pay\Models\QrCode::find($purchase->qr_code_id);

        return [
            'id'         => $purchase->id,
            'amount'     => $purchase->amount_bob,
            'coins'      => $purchase->totalCoins(),
            'wallet_bs'  => Money::label((int) $purchase->wallet_units),
            'wallet_units' => (int) $purchase->wallet_units,
            'lines'      => $purchase->lines,
            'qr_image'   => $qr?->qr_image ? 'data:image/png;base64,' . $qr->qr_image : null,
            'expires_at' => $purchase->expires_at->toIso8601String(),
        ];
    }

    public function onCheckPurchase(): array
    {
        $purchase = CreditPurchase::where('tenant_id', $this->tenantIdOrFail())->find((int) post('id'));

        return ['status' => $purchase?->status ?? 'not_found'];
    }

    public function onCancelPurchase(): array
    {
        if ($purchase = CreditPurchase::where('tenant_id', $this->tenantIdOrFail())->find((int) post('id'))) {
            Recharges::cancel($purchase);
        }

        return ['ok' => true];
    }

    // ---- Comprar monedas con el saldo en Bs ----

    public function onBuyWithWallet(): array
    {
        $tenantId = $this->tenantIdOrFail();

        try {
            $r = Credits::buyWithWallet($tenantId, (string) post('coin'), (int) post('coins'), BackendAuth::getUser()?->id);
        }
        catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new ApplicationException($e->getMessage());
        }

        return ['coins' => $r['coins']->delta, 'cost' => Money::format($r['cost_units'], 2, 'ceil'), 'wallet' => Money::format(Credits::walletUnits($tenantId))];
    }

    // ---- Intercambio ----

    public function onQuoteExchange(): array
    {
        try {
            $from = CreditType::findByCode((string) post('from'));
            $to = CreditType::findByCode((string) post('to'));

            if (!$from || !$to) {
                return ['error' => 'Elige las dos monedas.'];
            }

            return Credits::exchangeQuote($from, $to, (int) post('amount')) + ['error' => null];
        }
        catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function onExchange(): array
    {
        $tenantId = $this->tenantIdOrFail();

        try {
            $r = Credits::exchange($tenantId, (string) post('from'), (string) post('to'), (int) post('amount'), BackendAuth::getUser()?->id);
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException|\InvalidArgumentException|\RuntimeException $e) {
            throw new ApplicationException($e->getMessage());
        }

        return ['received' => $r['in']->delta, 'fee' => abs($r['fee']->delta), 'balances' => Credits::balances($tenantId)];
    }
}
