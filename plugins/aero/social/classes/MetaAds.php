<?php namespace Aero\Social\Classes;

use Aero\Social\Models\AdAccount;
use Aero\Social\Models\AdSet;
use Aero\Social\Models\Campaign;
use Aero\Social\Models\Insight;
use Aero\Social\Models\PostTarget;
use ApplicationException;
use Carbon\Carbon;
use Event;

/**
 * Meta Ads (Marketing API) para el tenant: conectar cuentas publicitarias,
 * sincronizar campañas/conjuntos/métricas, pausar/activar, cambiar presupuesto
 * y promocionar un post ya publicado. El gasto lo paga el tenant a Meta.
 */
class MetaAds
{
    /** Alta de las cuentas publicitarias a las que la persona dio acceso. */
    public static function connect(string $userToken, int $expiresIn, int $tenantId, int $userId): array
    {
        $rows = MetaClient::getAll('me/adaccounts', ['fields' => 'id,name,currency,timezone_name,account_status'], $userToken);
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row['id'])) {
                continue;
            }
            $account = AdAccount::firstOrNew(['tenant_id' => $tenantId, 'platform' => 'meta', 'external_id' => (string) $row['id']]);
            $account->fill([
                'name'             => $row['name'] ?? null,
                'currency'         => $row['currency'] ?? null,
                'timezone'         => $row['timezone_name'] ?? null,
                'account_status'   => $row['account_status'] ?? null,
                'access_token'     => $userToken,
                // Token de usuario de larga duración: ~60 días; luego hay que reconectar.
                'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : now()->addDays(60),
                'status'           => 'active',
                'last_error'       => null,
                'connected_by'     => $userId,
            ]);
            $account->save();
            $count++;

            try {
                self::sync($account, 30);
            } catch (SocialException $e) {
                // La conexión vale igual; la sincronización se reintenta cada hora.
            }
        }

        return ['ad_accounts' => $count];
    }

    /** Campañas, conjuntos y métricas de los últimos $days días. */
    public static function sync(AdAccount $account, int $days = 3): void
    {
        $token = (string) $account->access_token;

        try {
            $campaigns = MetaClient::getAll("{$account->external_id}/campaigns", [
                'fields' => 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,start_time,stop_time',
            ], $token);

            $byExternal = [];
            foreach ($campaigns as $c) {
                $campaign = Campaign::firstOrNew(['ad_account_id' => $account->id, 'external_id' => (string) $c['id']]);
                $campaign->fill([
                    'tenant_id'        => $account->tenant_id,
                    'name'             => $c['name'] ?? null,
                    'objective'        => $c['objective'] ?? null,
                    'status'           => $c['status'] ?? null,
                    'effective_status' => $c['effective_status'] ?? null,
                    'daily_budget'     => isset($c['daily_budget']) ? (int) $c['daily_budget'] : null,
                    'lifetime_budget'  => isset($c['lifetime_budget']) ? (int) $c['lifetime_budget'] : null,
                    'start_time'       => isset($c['start_time']) ? Carbon::parse($c['start_time']) : null,
                    'stop_time'        => isset($c['stop_time']) ? Carbon::parse($c['stop_time']) : null,
                    'synced_at'        => now(),
                ]);
                $campaign->save();
                $byExternal[$campaign->external_id] = $campaign;
            }

            $adsets = MetaClient::getAll("{$account->external_id}/adsets", [
                'fields' => 'id,name,campaign_id,status,effective_status,daily_budget,lifetime_budget',
            ], $token);
            foreach ($adsets as $a) {
                $campaign = $byExternal[(string) ($a['campaign_id'] ?? '')] ?? null;
                if (!$campaign) {
                    continue;
                }
                $set = AdSet::firstOrNew(['campaign_id' => $campaign->id, 'external_id' => (string) $a['id']]);
                $set->fill([
                    'tenant_id'        => $account->tenant_id,
                    'name'             => $a['name'] ?? null,
                    'status'           => $a['status'] ?? null,
                    'effective_status' => $a['effective_status'] ?? null,
                    'daily_budget'     => isset($a['daily_budget']) ? (int) $a['daily_budget'] : null,
                    'lifetime_budget'  => isset($a['lifetime_budget']) ? (int) $a['lifetime_budget'] : null,
                ]);
                $set->save();
            }

            $insights = MetaClient::getAll("{$account->external_id}/insights", [
                'level'          => 'campaign',
                'fields'         => 'campaign_id,spend,impressions,reach,clicks',
                'time_increment' => 1,
                'time_range'     => json_encode(['since' => now()->subDays($days)->toDateString(), 'until' => now()->toDateString()]),
            ], $token, 10000);
            foreach ($insights as $row) {
                $campaign = $byExternal[(string) ($row['campaign_id'] ?? '')] ?? null;
                if (!$campaign || empty($row['date_start'])) {
                    continue;
                }
                Insight::updateOrCreate(
                    ['campaign_id' => $campaign->id, 'date' => $row['date_start']],
                    [
                        'tenant_id'   => $account->tenant_id,
                        'spend'       => (float) ($row['spend'] ?? 0),
                        'impressions' => (int) ($row['impressions'] ?? 0),
                        'reach'       => (int) ($row['reach'] ?? 0),
                        'clicks'      => (int) ($row['clicks'] ?? 0),
                    ]
                );
            }

            $account->update(['synced_at' => now(), 'last_error' => null]);
        } catch (SocialException $e) {
            if ($e->tokenInvalid) {
                $account->markExpired($e->getMessage());
                Event::dispatch('aero.social.adAccountExpired', [$account]);
            } else {
                $account->update(['last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            }
            throw $e;
        }
    }

    public static function setStatus(Campaign $campaign, bool $active): void
    {
        $account = self::usable($campaign->adAccount);
        $status = $active ? 'ACTIVE' : 'PAUSED';

        MetaClient::post($campaign->external_id, ['status' => $status], $account->access_token);
        $campaign->update(['status' => $status, 'effective_status' => $status]);
        Event::dispatch('aero.social.campaignStatusChanged', [$campaign, $status]);
    }

    /** Cambia el presupuesto (en moneda de la cuenta) donde viva: campaña o su conjunto. */
    public static function setBudget(Campaign $campaign, float $amount): void
    {
        $account = self::usable($campaign->adAccount);
        $owner = $campaign->budgetOwner()
            ?? throw new ApplicationException('Esta campaña reparte el presupuesto en varios conjuntos: cámbialo desde el Administrador de anuncios de Meta.');

        if ($amount <= 0) {
            throw new ApplicationException('El presupuesto debe ser mayor que cero.');
        }

        $field = $owner->daily_budget ? 'daily_budget' : 'lifetime_budget';
        $minor = $account->toMinor($amount);

        MetaClient::post($owner->external_id, [$field => $minor], $account->access_token);
        $owner->update([$field => $minor]);
    }

    /**
     * Promociona un post publicado en Facebook o Instagram: campaña de
     * interacción + conjunto con presupuesto diario + anuncio con el post.
     */
    public static function boost(AdAccount $account, PostTarget $target, array $opts): Campaign
    {
        $account = self::usable($account);
        $source = $target->account;

        if ($target->status !== 'published' || !$target->external_id || !$source || !in_array($source->network, ['facebook', 'instagram'], true)) {
            throw new ApplicationException('Solo se pueden promocionar publicaciones ya publicadas en Facebook o Instagram.');
        }

        $daily = (float) ($opts['daily_budget'] ?? 0);
        $days = max(1, min(90, (int) ($opts['days'] ?? 7)));
        if ($daily <= 0) {
            throw new ApplicationException('Indica un presupuesto diario.');
        }

        $page = $source->network === 'facebook' ? $source : $source->parent;
        if (!$page) {
            throw new ApplicationException('No se encontró la página de Facebook vinculada a esta cuenta de Instagram.');
        }

        $token = $account->access_token;
        $act = $account->external_id;
        $name = 'Promoción: ' . mb_strimwidth(trim((string) $target->post?->content) ?: 'publicación', 0, 40, '…');
        $start = now()->addMinutes(5);

        $campaignId = self::id(MetaClient::post("{$act}/campaigns", [
            'name'                  => $name,
            'objective'             => 'OUTCOME_ENGAGEMENT',
            'status'                => 'ACTIVE',
            'special_ad_categories' => json_encode([]),
        ], $token));

        $targeting = [
            'geo_locations' => ['countries' => [strtoupper($opts['country'] ?? 'BO')]],
            'age_min'       => max(18, (int) ($opts['age_min'] ?? 18)),
            'age_max'       => min(65, (int) ($opts['age_max'] ?? 65)),
        ];
        if ($source->network === 'instagram') {
            $targeting['publisher_platforms'] = ['instagram'];
        }

        $adsetId = self::id(MetaClient::post("{$act}/adsets", [
            'name'              => $name,
            'campaign_id'       => $campaignId,
            'daily_budget'      => $account->toMinor($daily),
            'billing_event'     => 'IMPRESSIONS',
            'optimization_goal' => 'POST_ENGAGEMENT',
            'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
            'start_time'        => $start->toIso8601String(),
            'end_time'          => $start->copy()->addDays($days)->toIso8601String(),
            'targeting'         => json_encode($targeting),
            'status'            => 'ACTIVE',
        ], $token));

        $creative = $source->network === 'facebook'
            ? ['object_story_id' => self::storyId($page->external_id, $target->external_id)]
            : ['object_id' => $page->external_id, 'instagram_user_id' => $source->external_id, 'source_instagram_media_id' => $target->external_id];

        $creativeId = self::id(MetaClient::post("{$act}/adcreatives", ['name' => $name] + $creative, $token));

        MetaClient::post("{$act}/ads", [
            'name'     => $name,
            'adset_id' => $adsetId,
            'creative' => json_encode(['creative_id' => $creativeId]),
            'status'   => 'ACTIVE',
        ], $token);

        $campaign = Campaign::create([
            'tenant_id'      => $account->tenant_id,
            'ad_account_id'  => $account->id,
            'external_id'    => (string) $campaignId,
            'name'           => $name,
            'objective'      => 'OUTCOME_ENGAGEMENT',
            'status'         => 'ACTIVE',
            'effective_status' => 'IN_PROCESS',
            'start_time'     => $start,
            'stop_time'      => $start->copy()->addDays($days),
            'post_target_id' => $target->id,
            'synced_at'      => now(),
        ]);
        AdSet::create([
            'tenant_id' => $account->tenant_id, 'campaign_id' => $campaign->id, 'external_id' => (string) $adsetId,
            'name' => $name, 'status' => 'ACTIVE', 'daily_budget' => $account->toMinor($daily),
        ]);

        Event::dispatch('aero.social.postBoosted', [$campaign, $target]);

        return $campaign;
    }

    /** id de post de página: «{page}_{post}» (el de /photos o /videos puede venir sin prefijo). */
    public static function storyId(string $pageId, string $postId): string
    {
        return str_contains($postId, '_') ? $postId : "{$pageId}_{$postId}";
    }

    protected static function id(array $res): string
    {
        return (string) ($res['id'] ?? throw new SocialException('Meta no confirmó la creación del anuncio; revisa el Administrador de anuncios antes de reintentar.'));
    }

    protected static function usable(?AdAccount $account): AdAccount
    {
        if (!$account || !$account->isActive()) {
            throw new ApplicationException('La cuenta publicitaria no está conectada. Reconéctala en «Anuncios».');
        }

        return $account;
    }
}
