<?php namespace Aero\Connector\Classes;

use Aero\Connector\Models\Connector;

/**
 * Aplica el estilo de auth declarado por el tipo (none|bearer|basic|api_key_header|api_key_query)
 * a una petición saliente genérica. Los drivers de fábrica (HttpDriver) lo usan
 * directo; un driver a medida puede ignorarlo y armar sus propios headers.
 */
class AuthBuilder
{
    public static function apply(Connector $connector, array $headers, array $query): array
    {
        $type = TypeRegistry::find($connector->type) ?? [];
        $auth = $type['auth'] ?? 'none';
        $credentials = $connector->credentials;
        $config = (array) $connector->config;

        switch ($auth) {
            case 'bearer':
                if (!empty($credentials['token'])) {
                    $headers['Authorization'] = 'Bearer ' . $credentials['token'];
                }
                break;

            case 'basic':
                if (!empty($credentials['username'])) {
                    $headers['Authorization'] = 'Basic ' . base64_encode(
                        ($credentials['username'] ?? '') . ':' . ($credentials['password'] ?? '')
                    );
                }
                break;

            case 'api_key_header':
                if (!empty($credentials['api_key'])) {
                    $headers[$config['header_name'] ?? 'X-API-Key'] = $credentials['api_key'];
                }
                break;

            case 'api_key_query':
                if (!empty($credentials['api_key'])) {
                    $query[$config['query_param'] ?? 'api_key'] = $credentials['api_key'];
                }
                break;
        }

        return [$headers, $query];
    }
}
