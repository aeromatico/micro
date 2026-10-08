<?php namespace Aero\Connector\Classes;

use Aero\Connector\Models\Connector;

/**
 * Gateways de IA que hablan el protocolo de OpenAI Chat Completions y se
 * configuran con cabeceras propias. No son un tipo nuevo: son proveedores
 * (`provider_hint`) del tipo `ai_openai_compatible`, así los consumidores
 * (Chatbots, Workspaces, Sites…) los usan sin cambios.
 *
 * Portkey (cloud, o el gateway open source desplegado en un Worker de Cloudflare):
 *   api_key = llave del proveedor de IA (Authorization: Bearer), opcional con virtual key
 *   secret  = llave de Portkey (x-portkey-api-key), solo en Portkey cloud
 *   config  = portkey_provider, portkey_virtual_key, portkey_config, portkey_metadata
 *
 * Cloudflare AI Gateway (endpoint /compat, modelos como «openai/gpt-4o-mini»):
 *   api_key = llave del proveedor de IA (Authorization: Bearer), opcional con BYOK guardado en Cloudflare
 *   secret  = token del gateway autenticado (cf-aig-authorization)
 *   config  = account_id + gateway_id (arman la URL si base_url está vacía),
 *             cf_cache_ttl, cf_skip_cache, cf_max_attempts, cf_timeout_ms, cf_metadata
 */
class AiGateway
{
    public const PORTKEY = 'portkey';
    public const CLOUDFLARE = 'cloudflare_ai_gateway';

    public const PORTKEY_URL = 'https://api.portkey.ai/v1';

    public static function isGateway(?string $hint): bool
    {
        return in_array($hint, [self::PORTKEY, self::CLOUDFLARE], true);
    }

    /** URL base efectiva: la escrita por el usuario o, en Cloudflare, la armada con cuenta + gateway. */
    public static function baseUrl(Connector $connector): ?string
    {
        if ($connector->base_url) {
            return $connector->resolvedBaseUrl();
        }

        if ($connector->provider_hint === self::CLOUDFLARE) {
            $config = (array) $connector->config;
            $account = trim((string) ($config['account_id'] ?? ''));
            $gateway = trim((string) ($config['gateway_id'] ?? ''));

            if ($account !== '' && $gateway !== '') {
                return "https://gateway.ai.cloudflare.com/v1/{$account}/{$gateway}/compat";
            }
        }

        return null;
    }

    /** Cabeceras extra del gateway; vacío si el conector no usa uno. */
    public static function headers(Connector $connector): array
    {
        $config = (array) $connector->config;
        $secret = trim((string) ($connector->credentials['secret'] ?? ''));
        $headers = [];

        if ($connector->provider_hint === self::PORTKEY) {
            $map = [
                'portkey_provider'    => 'x-portkey-provider',
                'portkey_virtual_key' => 'x-portkey-virtual-key',
                'portkey_config'      => 'x-portkey-config',
            ];

            foreach ($map as $key => $header) {
                if (!empty($config[$key])) {
                    $headers[$header] = (string) $config[$key];
                }
            }

            if ($secret !== '') {
                $headers['x-portkey-api-key'] = $secret;
            }

            if (!empty($config['portkey_metadata'])) {
                $headers['x-portkey-metadata'] = static::json($config['portkey_metadata']);
            }
        }
        elseif ($connector->provider_hint === self::CLOUDFLARE) {
            if ($secret !== '') {
                $headers['cf-aig-authorization'] = 'Bearer ' . $secret;
            }

            if (isset($config['cf_cache_ttl']) && $config['cf_cache_ttl'] !== '') {
                $headers['cf-aig-cache-ttl'] = (string) (int) $config['cf_cache_ttl'];
            }

            if (!empty($config['cf_skip_cache'])) {
                $headers['cf-aig-skip-cache'] = 'true';
            }

            if (!empty($config['cf_max_attempts'])) {
                $headers['cf-aig-max-attempts'] = (string) (int) $config['cf_max_attempts'];
            }

            if (!empty($config['cf_timeout_ms'])) {
                $headers['cf-aig-request-timeout'] = (string) (int) $config['cf_timeout_ms'];
            }

            if (!empty($config['cf_metadata'])) {
                $headers['cf-aig-metadata'] = static::json($config['cf_metadata']);
            }
        }

        return $headers;
    }

    /** Qué falta para poder llamar, o null si está completo. */
    public static function missing(Connector $connector): ?string
    {
        if (!static::isGateway($connector->provider_hint)) {
            return null;
        }

        if (!static::baseUrl($connector)) {
            return $connector->provider_hint === self::CLOUDFLARE
                ? 'Falta la URL base o config.account_id + config.gateway_id del AI Gateway de Cloudflare.'
                : 'Falta la URL base del gateway de Portkey.';
        }

        $config = (array) $connector->config;
        $apiKey = $connector->credentials['api_key'] ?? null;

        if ($connector->provider_hint === self::PORTKEY && !$apiKey && empty($config['portkey_virtual_key']) && empty($config['portkey_config'])) {
            return 'Portkey necesita la API Key del proveedor, una virtual key (config.portkey_virtual_key) o un config id (config.portkey_config).';
        }

        return null;
    }

    protected static function json(mixed $value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
