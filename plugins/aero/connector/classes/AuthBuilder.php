<?php namespace Aero\Connector\Classes;

use Aero\Connector\Models\Connector;

/**
 * Arma el auth de una petición saliente genérica (usado por HttpDriver) a
 * partir de las credenciales estandarizadas del Connector, sin depender de
 * un "tipo" elegido a mano: api_key + secret → Basic; solo api_key → Bearer
 * (igual que los drivers de IA). Estilos menos comunes (header/query con
 * nombre custom) siguen disponibles vía `config.auth_style` en el JSON
 * avanzado, para no forzarle ese detalle a un dropdown en el flujo normal.
 */
class AuthBuilder
{
    public static function apply(Connector $connector, array $headers, array $query): array
    {
        $credentials = $connector->credentials;
        $config = (array) $connector->config;
        $apiKey = $credentials['api_key'] ?? null;
        $secret = $credentials['secret'] ?? null;
        $style = $config['auth_style'] ?? null;

        if ($style === 'api_key_header' && $apiKey) {
            $headers[$config['header_name'] ?? 'X-API-Key'] = $apiKey;
        }
        elseif ($style === 'api_key_query' && $apiKey) {
            $query[$config['query_param'] ?? 'api_key'] = $apiKey;
        }
        elseif ($apiKey && $secret) {
            $headers['Authorization'] = 'Basic ' . base64_encode("{$apiKey}:{$secret}");
        }
        elseif ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        return [$headers, $query];
    }
}
