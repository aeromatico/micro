<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Envoltorio delgado sobre `wp_remote_*` para hablar con
 * `api/v1/pay` (Aero\Pay\Http\Controllers\Api\QrCodesController).
 *
 * Se instancia con la URL base y la API key configuradas en los ajustes de
 * la pasarela — nunca lee opciones directamente, para poder testearla o
 * reusarla desde otro contexto (el poller de estado, el webhook) sin
 * acoplarse a WC_Settings_API.
 */
class Aero_Pay_Api_Client
{
    private string $base_url;

    private string $api_key;

    public function __construct(string $base_url, string $api_key)
    {
        $this->base_url = untrailingslashit(trim($base_url));
        $this->api_key = trim($api_key);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, data: array|null, error: string|null, message: string|null}
     */
    public function create_qr(array $payload): array
    {
        return $this->request('POST', '/api/v1/pay/qr', $payload);
    }

    public function get_qr(int $qr_id): array
    {
        return $this->request('GET', '/api/v1/pay/qr/' . $qr_id);
    }

    public function cancel_qr(int $qr_id): array
    {
        return $this->request('DELETE', '/api/v1/pay/qr/' . $qr_id);
    }

    public function public_image_url(string $internal_reference): string
    {
        return $this->base_url . '/api/v1/pay/public/qr/' . rawurlencode($internal_reference) . '/image';
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{ok: bool, status: int, data: array|null, error: string|null, message: string|null}
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept'        => 'application/json',
            ],
        ];

        if ($payload !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($this->base_url . $path, $args);

        if (is_wp_error($response)) {
            return [
                'ok'      => false,
                'status'  => 0,
                'data'    => null,
                'error'   => 'connection_error',
                'message' => $response->get_error_message(),
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $ok = $status >= 200 && $status < 300;

        return [
            'ok'      => $ok,
            'status'  => $status,
            'data'    => $ok ? ($body['data'] ?? null) : null,
            'error'   => !$ok ? ($body['error'] ?? 'http_error') : null,
            'message' => !$ok ? ($body['message'] ?? null) : null,
        ];
    }
}
