<?php namespace Aero\WpFlash\Classes;

use ApplicationException;
use Str;
use Aero\Connector\Models\Connector;
use Aero\Sites\Models\Tenant;
use Aero\WpFlash\Drivers\WooCommerceDriver;
use Aero\WpFlash\Models\Settings;
use Aero\WpFlash\Models\SiteInstance;

/**
 * Orquesta el alta de WordPress Flash para un tenant: crea el childsite y su
 * admin por WP-CLI (mismo mecanismo que bin/nuevo-sitio.sh en
 * wp.market.com.bo, ver Classes\WpCli), arma el Connector WooCommerce local
 * con un application password de WordPress, registra sus webhooks, y apunta
 * el dominio del tenant vía Classes\DomainRouter.
 */
class Provisioner
{
    protected const WEBHOOK_TOPICS = [
        'product'  => ['product.created', 'product.updated', 'product.deleted'],
        'customer' => ['customer.created', 'customer.updated'],
    ];

    public function provision(Tenant $tenant): SiteInstance
    {
        $wp = app(WpCli::class);

        if (!$wp->isWooCommerceNetworkActive()) {
            throw new ApplicationException(
                'WooCommerce no está activo en la red de WordPress. Es un paso único: en el servidor, '
                . '"wp plugin install woocommerce --activate-network --path=' . Settings::wpPath() . '" (ver README.md).'
            );
        }

        $site = SiteInstance::firstOrNew(['tenant_id' => $tenant->id]);
        $site->status = 'provisioning';
        $site->save();

        $slug = $tenant->handle;
        $username = 'wpflash_' . $tenant->id;
        $email = $tenant->backendUser?->email ?: "tenant{$tenant->id}@market.com.bo";
        $password = Str::random(16);
        $childUrl = "https://{$slug}." . Settings::wpNetworkDomain() . '/';

        $wp->ensureUser($username, $email, $password);

        $blogId = $wp->createSite($slug, $tenant->name, $email);

        if (!$blogId) {
            $site->status = 'error';
            $site->error_message = 'WP-CLI no pudo crear el sitio (¿el slug ya existe en la red?).';
            $site->save();

            throw new ApplicationException($site->error_message);
        }

        $appPassword = $wp->createApplicationPassword($username, 'Aero Shop Sync', $childUrl);
        $webhookSecret = Str::random(40);

        $wooConnector = Connector::create([
            'name'          => "WooCommerce — {$tenant->name}",
            'provider_hint' => 'woocommerce',
            'base_url'      => rtrim($childUrl, '/'),
            'owner_type'    => Tenant::class,
            'owner_id'      => $tenant->id,
            'credentials'   => [
                'api_key'        => $username,
                'secret'         => $appPassword,
                'webhook_secret' => $webhookSecret,
            ],
        ]);

        $site->fill([
            'wp_site_id'     => $blogId,
            'wp_admin_url'   => $childUrl,
            'primary_domain' => "{$slug}." . Settings::wpNetworkDomain(),
            'admin_username' => $username,
            'connector_id'   => $wooConnector->id,
            'status'         => 'active',
            'error_message'  => null,
        ]);
        $site->admin_password = $password;
        $site->save();

        $this->registerWebhooks($wooConnector, $webhookSecret);

        app(DomainRouter::class)->pointToWordPress($tenant, $site->fresh());

        return $site->fresh();
    }

    public function deprovision(Tenant $tenant): void
    {
        $site = SiteInstance::where('tenant_id', $tenant->id)->first();
        if (!$site) {
            return;
        }

        app(DomainRouter::class)->pointToPlatform($site);

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
