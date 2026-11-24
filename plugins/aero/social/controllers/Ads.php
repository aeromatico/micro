<?php namespace Aero\Social\Controllers;

use Aero\Social\Classes\CurrentTenant;
use Aero\Social\Classes\MetaAds;
use Aero\Social\Classes\MetaClient;
use Aero\Social\Classes\SocialException;
use Aero\Social\Models\AdAccount;
use Aero\Social\Models\Campaign;
use Aero\Social\Models\Insight;
use ApplicationException;
use Backend;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/**
 * Anuncios: cuentas publicitarias del tenant, KPIs y campañas con acciones
 * rápidas. Todo se filtra por el tenant actual (falla cerrado).
 */
class Ads extends Controller
{
    public $requiredPermissions = ['aero.social.ads', 'aero.social.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Social', 'social', 'ads');
    }

    public function index()
    {
        $this->pageTitle = 'Anuncios';
        $tenantId = CurrentTenant::id();
        $accounts = AdAccount::forTenant($tenantId)->where('status', '!=', 'disabled')->orderBy('name')->get();
        $current = $accounts->firstWhere('id', (int) get('account')) ?? $accounts->firstWhere('status', 'active') ?? $accounts->first();
        $days = in_array((int) get('days'), [7, 30, 90], true) ? (int) get('days') : 30;

        $this->vars += [
            'tenantId'  => $tenantId,
            'metaReady' => MetaClient::isConfigured(),
            'accounts'  => $accounts,
            'current'   => $current,
            'days'      => $days,
            'kpis'      => $current ? $this->kpis($current, $days) : null,
            'campaigns' => $current ? $this->campaigns($current, $days) : collect(),
        ];
    }

    protected function kpis(AdAccount $account, int $days): array
    {
        $row = Insight::whereIn('campaign_id', $account->campaigns()->select('id'))
            ->where('date', '>=', now()->subDays($days - 1)->toDateString())
            ->selectRaw('COALESCE(SUM(spend),0) spend, COALESCE(SUM(impressions),0) impressions, COALESCE(SUM(reach),0) reach, COALESCE(SUM(clicks),0) clicks')
            ->first();

        $spend = (float) $row->spend;
        $clicks = (int) $row->clicks;
        $impr = (int) $row->impressions;

        return [
            'spend'       => $spend,
            'impressions' => $impr,
            'reach'       => (int) $row->reach,
            'clicks'      => $clicks,
            'cpc'         => $clicks ? $spend / $clicks : null,
            'ctr'         => $impr ? $clicks / $impr * 100 : null,
        ];
    }

    protected function campaigns(AdAccount $account, int $days)
    {
        $since = now()->subDays($days - 1)->toDateString();

        return $account->campaigns()
            ->whereNotIn('status', ['DELETED', 'ARCHIVED'])
            ->withSum(['insights as spend_sum' => fn ($q) => $q->where('date', '>=', $since)], 'spend')
            ->withSum(['insights as clicks_sum' => fn ($q) => $q->where('date', '>=', $since)], 'clicks')
            ->withSum(['insights as impressions_sum' => fn ($q) => $q->where('date', '>=', $since)], 'impressions')
            ->orderByRaw("CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END")
            ->orderByDesc('spend_sum')
            ->limit(200)
            ->get();
    }

    /** Botón «Conectar Meta Ads». */
    public function connect()
    {
        if (!\Aero\Social\Classes\MetaConnect::onCanonicalHost()) {
            return redirect()->away(\Aero\Social\Classes\MetaConnect::canonical('aero/social/ads/connect'));
        }

        $tenantId = CurrentTenant::id();
        if (!$tenantId) {
            Flash::error('Tu usuario no pertenece a ninguna cuenta.');

            return Backend::redirect('aero/social/ads');
        }
        if (!MetaClient::isConfigured()) {
            Flash::error('La conexión con Meta aún no está habilitada en la plataforma.');

            return Backend::redirect('aero/social/ads');
        }

        return redirect()->away(\Aero\Social\Classes\MetaConnect::authorizeUrl($tenantId, \BackendAuth::getUser()->id, 'ads'));
    }

    protected function account(): AdAccount
    {
        return AdAccount::forTenant(CurrentTenant::id())->findOrFail((int) post('account_id'));
    }

    protected function campaign(): Campaign
    {
        $campaign = Campaign::where('tenant_id', CurrentTenant::id() ?: -1)->findOrFail((int) post('campaign_id'));

        return $campaign;
    }

    protected function back(?int $accountId = null)
    {
        return Backend::redirect('aero/social/ads' . ($accountId ? '?account=' . $accountId : ''));
    }

    public function index_onSync()
    {
        $account = $this->account();
        try {
            MetaAds::sync($account, 30);
            Flash::success('Datos actualizados.');
        } catch (SocialException $e) {
            Flash::error($e->getMessage());
        }

        return $this->back($account->id);
    }

    public function index_onToggle()
    {
        $campaign = $this->campaign();
        $this->guard(fn () => MetaAds::setStatus($campaign, !$campaign->isActive()));
        Flash::success($campaign->isActive() ? 'Campaña activada.' : 'Campaña pausada.');

        return $this->back($campaign->ad_account_id);
    }

    public function index_onBudgetForm()
    {
        $campaign = $this->campaign();
        $owner = $campaign->budgetOwner();

        return $this->makePartial('budget_form', [
            'campaign' => $campaign,
            'owner'    => $owner,
            'account'  => $campaign->adAccount,
        ]);
    }

    public function index_onBudget()
    {
        $campaign = $this->campaign();
        $amount = (float) str_replace(',', '.', (string) post('amount'));
        $this->guard(fn () => MetaAds::setBudget($campaign, $amount));
        Flash::success('Presupuesto actualizado.');

        return $this->back($campaign->ad_account_id);
    }

    public function index_onDisconnect()
    {
        $account = $this->account();
        $account->update(['status' => 'disabled', 'access_token' => null]);
        Flash::success('Cuenta publicitaria desconectada. Las campañas siguen corriendo en Meta: páusalas antes si no quieres gastar.');

        return $this->back();
    }

    /** Errores de Meta → mensaje; token muerto → cuenta vencida. */
    protected function guard(\Closure $action): void
    {
        try {
            $action();
        } catch (SocialException $e) {
            if ($e->tokenInvalid && ($campaign = Campaign::find((int) post('campaign_id')))) {
                $campaign->adAccount?->markExpired($e->getMessage());
            }
            throw new ApplicationException($e->getMessage());
        }
    }
}
