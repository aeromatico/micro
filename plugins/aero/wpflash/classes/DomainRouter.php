<?php namespace Aero\WpFlash\Classes;

use ApplicationException;
use Aero\Sites\Models\Tenant;
use Aero\WpFlash\Models\Settings;
use Aero\WpFlash\Models\SiteInstance;

/**
 * "Apuntar el subdominio" NO es un cambio de DNS: `tenant.market.com.bo` ya
 * resuelve a este mismo servidor (es la plataforma). WordPress Multisite
 * decide qué sitio servir por el header Host, así que lo que hace falta es:
 *
 *   1. Domain mapping en WordPress — update_blog_details() cambia el dominio
 *      con el que ESE childsite responde, de `{slug}.wp.market.com.bo` al
 *      dominio real del tenant (ver Classes\WpCli::remapDomain()).
 *   2. Que nginx enrute ese Host al docroot de WordPress en vez de al de esta
 *      plataforma. La plataforma sirve *.market.com.bo por wildcard —
 *      agregar un server{} nuevo con el/los dominios exactos del tenant
 *      gana esa ruta sobre el wildcard (nginx prioriza nombre exacto sobre
 *      comodín) sin tocar el vhost existente. Ese archivo lo genera
 *      regenerateNginxConfig() en storage/app (escribible por `www`); un cron
 *      de root lo aplica — ver deploy/apply-nginx.sh y README.md.
 */
class DomainRouter
{
    public function pointToWordPress(Tenant $tenant, SiteInstance $site): void
    {
        $domain = $tenant->handle . '.' . ($tenant->rootDomain?->domain ?: 'market.com.bo');
        $wp = app(WpCli::class);

        $ok = $wp->updateOption($site->wp_admin_url, 'home', "https://{$domain}")
            && $wp->updateOption($site->wp_admin_url, 'siteurl', "https://{$domain}")
            && $wp->remapDomain($site->wp_admin_url, $domain);

        if (!$ok) {
            throw new ApplicationException('El childsite se creó, pero no se pudo apuntar el dominio del tenant en WordPress.');
        }

        $site->primary_domain = $domain;
        $site->saveQuietly();

        static::regenerateNginxConfig();
    }

    public function pointToPlatform(SiteInstance $site): void
    {
        $wp = app(WpCli::class);

        // Se direcciona por el dominio ACTUAL del tenant (el mapeado), porque
        // es el único por el que WordPress todavía reconoce este sitio.
        $currentUrl = "https://{$site->primary_domain}/";
        $fallbackUrl = $site->wp_admin_url;

        $wp->updateOption($currentUrl, 'home', $fallbackUrl);
        $wp->updateOption($currentUrl, 'siteurl', $fallbackUrl);
        $wp->remapDomain($currentUrl, parse_url($fallbackUrl, PHP_URL_HOST));

        $site->primary_domain = parse_url($fallbackUrl, PHP_URL_HOST);
        $site->saveQuietly();

        static::regenerateNginxConfig();
    }

    /**
     * Escribe el server{} que le gana al wildcard de la plataforma para los
     * dominios de tenants con WordPress Flash activo, en un archivo que
     * `www` sí puede escribir (nunca directo en /www/server/panel/vhost —
     * ver deploy/apply-nginx.sh).
     */
    public static function regenerateNginxConfig(): void
    {
        $domains = SiteInstance::active()->pluck('primary_domain')->filter()->values();

        $path = storage_path('app/wpflash/tenants.conf');
        @mkdir(dirname($path), 0755, true);

        if ($domains->isEmpty()) {
            file_put_contents($path, "# Aero.WpFlash: sin tenants activos.\n");
            return;
        }

        $serverNames = $domains->implode(' ');
        $wpPath = Settings::wpPath();

        file_put_contents($path, <<<CONF
        # Generado por Aero.WpFlash — Classes\\DomainRouter::regenerateNginxConfig().
        # NO editar a mano: se sobreescribe en cada activar/desactivar y por el cron
        # wpflash:nginx-sync. Aplicado a /www/server/panel/vhost/nginx por
        # deploy/apply-nginx.sh (root, fuera de esta app).
        server {
            listen 80;
            listen 443 ssl http2;
            server_name {$serverNames};
            root {$wpPath};
            index index.php;

            ssl_certificate     /www/server/panel/vhost/cert/market.com.bo/fullchain.pem;
            ssl_certificate_key /www/server/panel/vhost/cert/market.com.bo/privkey.pem;

            location / {
                try_files \$uri \$uri/ /index.php?\$args;
            }

            location ~ [^/]\\.php(/|\$) {
                fastcgi_pass unix:/tmp/php-cgi-84.sock;
                fastcgi_index index.php;
                include fastcgi.conf;
            }

            location ~ ^/(\\.user\\.ini|\\.htaccess|\\.git|\\.env) {
                return 404;
            }

            access_log /www/wwwlogs/aero-wpflash.log;
            error_log  /www/wwwlogs/aero-wpflash.error.log;
        }
        CONF);
    }
}
