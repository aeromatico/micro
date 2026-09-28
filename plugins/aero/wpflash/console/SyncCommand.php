<?php namespace Aero\WpFlash\Console;

use Illuminate\Console\Command;
use Aero\Connector\Classes\ConnectorClient;
use Aero\WpFlash\Classes\CustomerSync;
use Aero\WpFlash\Classes\ProductSync;
use Aero\WpFlash\Drivers\WooCommerceDriver;
use Aero\WpFlash\Models\SiteInstance;

/**
 * Respaldo de los webhooks en tiempo real: si WordPress estuvo caído o un
 * webhook se perdió, este comando (cron cada 15 min, ver Plugin::
 * registerSchedule()) reconcilia lo que falte pidiendo directo a la API de
 * WooCommerce lo modificado desde el último sync.
 */
class SyncCommand extends Command
{
    protected $signature = 'wpflash:sync';

    protected $description = 'Reconcilia productos/clientes de WooCommerce con Aero.Shop para todos los sitios WP Flash activos.';

    public function handle(): int
    {
        $driver = app(WooCommerceDriver::class);
        $client = app(ConnectorClient::class);

        foreach (SiteInstance::active()->with('connector')->get() as $site) {
            if (!$site->connector) {
                continue;
            }

            $since = $site->last_synced_at?->toIso8601String();

            $products = $driver->listProducts($site->connector, $since ? ['modified_after' => $since] : []);
            foreach ((array) $products->body as $product) {
                ProductSync::handle($site->tenant_id, 'product.updated', (array) $product);
            }

            $customers = $driver->listCustomers($site->connector, $since ? ['modified_after' => $since] : []);
            foreach ((array) $customers->body as $customer) {
                CustomerSync::handle($site->tenant_id, 'customer.updated', (array) $customer);
            }

            $site->last_synced_at = now();
            $site->saveQuietly();
        }

        return self::SUCCESS;
    }
}
