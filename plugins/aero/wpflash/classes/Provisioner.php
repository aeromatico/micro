<?php namespace Aero\WpFlash\Classes;

use ApplicationException;
use Str;
use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Models\Connector;
use Aero\Sites\Models\Tenant;
use Aero\WpFlash\Drivers\WooCommerceDriver;
use Aero\WpFlash\Models\Settings;
use Aero\WpFlash\Models\SiteInstance;

/**
 * Orquesta el alta de WordPress Flash para un tenant: crea el childsite (vía
 * el Connector de red, contra el mu-plugin de WordPress — ver README.md),
 * arma el Connector WooCommerce local, registra sus webhooks, y apunta el
 * subdominio vía Classes\DomainRouter. Todo en un solo método porque, a
 * diferencia de OrderService (que sí necesita ser reentrante/transaccional),
 * este flujo es un proceso de una sola vez por tenant, disparado a mano desde
 * el backend.
 */
class Provisioner
{
    /** @var string[] Tópicos que WooCommerce debe entregar en tiempo real. */
    protected const WEBHOOK_TOPICS = [
        'product'  => ['product.created', 'product.updated', 'product.deleted'],
        'customer' => ['customer.created', 'customer.updated'],
    ];

    public function provision(Tenant $tenant): SiteInstance
    {
        $networkConnector = Settings::networkConnector();
        if (!$networkConnector) {
            throw new ApplicationException('No hay un Connector de red configurado en Ajustes → WpFlash (ver README.md de Aero.WpFlash).');
        }

        $site = SiteInstance::firstOrNew(['tenant_id' => $tenant->id]);
        $site->status = 'provisioning';
        $site->save();

        $domain = $tenant->handle . '.' . ($tenant->rootDomain?->domain ?: 'market.com.bo');
        $adminEmail = $tenant->backendUser?->email ?: "tenant{$tenant->id}@market.com.bo";

        $response = app(ConnectorClient::class)->send($networkConnector, [
            'domain'      => $domain,
            'admin_email' => $adminEmail,
        ]);

        if (!$response->successful) {
            $site->status = 'error';
            $site->error_message = $response->error ?: 'El puente de WordPress no pudo crear el childsite.';
            $site->save();

            throw new ApplicationException($site->error_message);
        }

        $body = is_array($response->body) ? $response->body : [];
        $webhookSecret = Str::random(40);

        $wooConnector = Connector::create([
            'name'          => "WooCommerce — {$tenant->name}",
            'provider_hint' => 'woocommerce',
            'base_url'      => rtrim((string) ($body['admin_url'] ?? "https://{$domain}"), '/'),
            'owner_type'    => Tenant::class,
            'owner_id'      => $tenant->id,
            'credentials'   => [
                'api_key'        => $body['consumer_key'] ?? null,
                'secret'         => $body['consumer_secret'] ?? null,
                'webhook_secret' => $webhookSecret,
            ],
        ]);

        $site->fill([
            'wp_site_id'     => $body['wp_site_id'] ?? null,
            'wp_admin_url'   => $body['admin_url'] ?? "https://{$domain}",
            'primary_domain' => $domain,
            'admin_username' => $body['admin_username'] ?? 'admin',
            'connector_id'   => $wooConnector->id,
            'status'         => 'active',
            'error_message'  => null,
        ]);
        $site->admin_password = $body['admin_password'] ?? null;
        $site->save();

        $this->registerWebhooks($wooConnector, $webhookSecret);

        $dns = app(DomainRouter::class)->pointToWordPress($tenant);
        if (!($dns['ok'] ?? false) && empty($dns['manual'])) {
            $site->error_message = 'Sitio creado, pero el DNS no se pudo apuntar automáticamente: ' . ($dns['message'] ?? '');
            $site->save();
        }

        return $site;
    }

    public function deprovision(Tenant $tenant): void
    {
        $site = SiteInstance::where('tenant_id', $tenant->id)->first();
        if (!$site) {
            return;
        }

        app(DomainRouter::class)->pointToPlatform($tenant);

        $site->status = 'suspended';
        $site->save();
    }

    protected function registerWebhooks(Connector $wooConnector, string $secret): void
    {
        $driver = app(WooCommerceDriver::class);

        foreach (static::WEBHOOK_TOPICS as $group => $topics) {
            $deliveryUrl = url("connector/webhooks/wpflash-{$group}");

            foreach ($topics as $topic) {
                $driver->registerWebhook($wooConnector, $topic, $deliveryUrl, $secret);
            }
        }
    }
}
