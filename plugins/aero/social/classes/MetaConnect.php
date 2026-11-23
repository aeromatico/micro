<?php namespace Aero\Social\Classes;

use Aero\Social\Models\Account;
use Backend;
use Illuminate\Support\Str;

/**
 * «Conectar con Meta»: OAuth de Facebook Login. El tenant elige sus páginas
 * en el diálogo de Meta; guardamos el token de cada página (no vence si sale
 * de un token de usuario de larga duración) y su Instagram vinculado.
 */
class MetaConnect
{
    public const SESSION_KEY = 'aero_social.meta_connect';

    public const SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'instagram_basic',
        'instagram_content_publish',
        'business_management',
    ];

    /** Permisos para Anuncios (se piden aparte: Meta los revisa con más rigor). */
    public const ADS_SCOPES = [
        'ads_management',
        'ads_read',
        'business_management',
        'pages_show_list',
        'pages_read_engagement',
    ];

    public static function redirectUri(): string
    {
        return config('aero.social::meta.redirect_uri') ?: Backend::url('aero/social/connections/callback');
    }

    /** $purpose: 'pages' (publicar) o 'ads' (anuncios). */
    public static function authorizeUrl(int $tenantId, int $userId, string $purpose = 'pages'): string
    {
        $state = Str::random(40);
        session()->put(self::SESSION_KEY, ['state' => $state, 'tenant_id' => $tenantId, 'user_id' => $userId, 'purpose' => $purpose]);

        return 'https://www.facebook.com/' . MetaClient::version() . '/dialog/oauth?' . http_build_query([
            'client_id'     => MetaClient::appId(),
            'redirect_uri'  => self::redirectUri(),
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => implode(',', $purpose === 'ads' ? self::ADS_SCOPES : self::SCOPES),
            // Volver a mostrar la elección de páginas aunque ya haya autorizado antes.
            'auth_type'     => 'rerequest',
        ]);
    }

    /**
     * Procesa el retorno de Meta y sincroniza las cuentas del tenant.
     *
     * @return array{purpose:string, ...}
     * @throws SocialException
     */
    public static function callback(array $query, int $tenantId, int $userId): array
    {
        $pending = session()->pull(self::SESSION_KEY);

        if (!$pending
            || !hash_equals((string) $pending['state'], (string) ($query['state'] ?? ''))
            || (int) $pending['tenant_id'] !== $tenantId
            || (int) $pending['user_id'] !== $userId) {
            throw new SocialException('La conexión expiró o no es válida. Inténtalo otra vez.');
        }
        if (!empty($query['error']) || empty($query['code'])) {
            throw new SocialException('Se canceló la conexión con Meta.');
        }

        $short = MetaClient::get('oauth/access_token', [
            'client_id'     => MetaClient::appId(),
            'client_secret' => MetaClient::appSecret(),
            'redirect_uri'  => self::redirectUri(),
            'code'          => $query['code'],
        ]);

        $long = MetaClient::get('oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => MetaClient::appId(),
            'client_secret'     => MetaClient::appSecret(),
            'fb_exchange_token' => $short['access_token'],
        ]);

        if (($pending['purpose'] ?? 'pages') === 'ads') {
            return ['purpose' => 'ads'] + MetaAds::connect($long['access_token'], (int) ($long['expires_in'] ?? 0), $tenantId, $userId);
        }

        $pages = MetaClient::get('me/accounts', [
            'fields' => 'id,name,access_token,picture{url},instagram_business_account{id,username,name,profile_picture_url}',
            'limit'  => 100,
        ], $long['access_token']);

        return ['purpose' => 'pages'] + self::sync($pages['data'] ?? [], $tenantId, $userId);
    }

    /** Alta/actualización de páginas e Instagram del tenant. Público para tests. */
    public static function sync(array $pages, int $tenantId, int $userId): array
    {
        $counts = ['facebook' => 0, 'instagram' => 0, 'skipped' => 0];
        $sync = new AccountSync($tenantId);

        foreach ($pages as $page) {
            if (empty($page['id']) || empty($page['access_token'])) {
                continue;
            }

            $ig = $page['instagram_business_account'] ?? null;
            if (!$sync->fits('facebook', (string) $page['id'])) {
                $sync->skipped += empty($ig['id']) ? 0 : 1;   // su Instagram tampoco entra
                continue;
            }

            $fb = $sync->upsert('facebook', (string) $page['id'], [
                'name'         => $page['name'] ?? null,
                'avatar_url'   => $page['picture']['data']['url'] ?? null,
                'access_token' => $page['access_token'],
                'connected_by' => $userId,
            ]);
            $counts['facebook']++;

            if ($ig && !empty($ig['id']) && $sync->fits('instagram', (string) $ig['id'])) {
                $sync->upsert('instagram', (string) $ig['id'], [
                    'name'         => $ig['name'] ?? $ig['username'] ?? null,
                    'username'     => $ig['username'] ?? null,
                    'avatar_url'   => $ig['profile_picture_url'] ?? null,
                    'access_token' => $page['access_token'],
                    'parent_id'    => $fb->id,
                    'connected_by' => $userId,
                ]);
                $counts['instagram']++;
            }
        }

        $counts['skipped'] = $sync->skipped;

        return $counts;
    }
}
