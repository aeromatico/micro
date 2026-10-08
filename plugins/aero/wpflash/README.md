# Aero.WpFlash — WordPress Flash

Motor de sitio alternativo para tenants acostumbrados a WordPress/WooCommerce.
Al activarlo, el tenant recibe un childsite en nuestro WordPress Multisite
(`wp.market.com.bo`, mismo servidor que esta plataforma), su subdominio pasa
a servir ese childsite, y el catálogo (productos/clientes) se sincroniza en
tiempo real y en un solo sentido: **WooCommerce manda siempre**, Aero.Shop
recibe.

## Fase 1 — alcance

- ✅ Provisioning automático del childsite por WP-CLI (nombre = subdominio
  del tenant, usuario/contraseña de admin generados, mostrados una sola vez).
- ✅ Enrutamiento del subdominio del tenant hacia WordPress (domain mapping +
  nginx, ver más abajo — no es un cambio de DNS).
- ✅ Sync en tiempo real de productos y clientes de WooCommerce → Aero.Shop,
  vía webhooks + reconciliación periódica de respaldo.
- ❌ Fuera de alcance: sync de pedidos hacia WP, dominios externos del tenant
  (fuera de `*.market.com.bo` — necesitan su propio análisis de enrutamiento),
  sync de contenido/páginas, cupones/reseñas de WooCommerce.

## Por qué no es "solo un CNAME en Cloudflare"

WordPress Multisite decide qué sitio servir por el **header Host** de la
petición, no por resolución DNS. `tenant.market.com.bo` ya resuelve a este
mismo servidor (la wildcard `*.market.com.bo` que usa toda la plataforma) —
no hace falta ni se puede arreglar esto con un registro DNS nuevo. Lo que
hace falta es:

1. **Domain mapping en WordPress**: `update_blog_details()` (WP core, vía
   WP-CLI `wp eval`, en `Classes\WpCli::remapDomain()`) cambia el dominio con
   el que ESE childsite responde, de `{slug}.wp.market.com.bo` al dominio
   real del tenant.
2. **nginx enruta ese Host a WordPress**: la plataforma sirve `*.market.com.bo`
   por un wildcard (`market.com.bo.conf`). `Classes\DomainRouter::regenerateNginxConfig()`
   escribe un `server{}` nuevo y aparte, con los dominios exactos de los
   tenants con WordPress Flash activo — nginx prioriza un `server_name`
   exacto sobre uno con comodín, así que ese archivo nuevo "gana" la ruta sin
   tocar `market.com.bo.conf` ni `wp.market.com.bo.conf` para nada. Cuando un
   tenant se desactiva, simplemente sale de la lista y nginx vuelve a
   servirlo con la plataforma normal — sin ningún "switch" explícito de por
   medio.

## Por qué un script aparte para nginx (`deploy/apply-nginx.sh`)

October corre como `www`, que no es dueño de `/www/server/panel/vhost` ni
puede recargar nginx — y no debería poder, es demasiado radio de acción para
un proceso web. Así que el plugin solo escribe el archivo que *quiere* aplicar
en `storage/app/wpflash/tenants.conf` (dentro de su propio storage, sin
privilegios especiales). `deploy/apply-nginx.sh` es el único punto que copia
ese archivo a `/www/server/panel/vhost/nginx/aero-wpflash.conf` y recarga
nginx — se instala **una sola vez** como cron de root:

```bash
crontab -e
* * * * * /www/wwwroot/micro.clouds.com.bo/plugins/aero/wpflash/deploy/apply-nginx.sh >> /var/log/aero-wpflash-nginx.log 2>&1
```

## Configuración inicial (una sola vez, manual)

1. Instalar el cron de `deploy/apply-nginx.sh` (arriba).
2. **Network Admin de WordPress** (`https://wp.market.com.bo/wp-admin/network/plugins.php`):
   instalar y activar WooCommerce **a nivel red** una sola vez —
   `wp plugin install woocommerce --activate-network --path=/www/wwwroot/wp.market.com.bo`.
   Al estar activado en red, cada childsite nuevo lo tiene automáticamente,
   sin ningún paso extra por tenant.
3. Revisar **Backend → Aero.WpFlash → Ajustes** (rutas de WP-CLI, casi nunca
   hace falta tocarlas — vienen con los valores reales del servidor).

## Provisioning (`Classes\Provisioner`)

Todo por WP-CLI (mismo mecanismo que `bin/nuevo-sitio.sh`, ya en uso manual
en `wp.market.com.bo`), sin HTTP ni mu-plugin adicional:

1. Crea un usuario admin de WordPress con contraseña conocida
   (`wp user create ... --user_pass=...`).
2. Crea el sitio (`wp site create --slug=... --email=<ese usuario>`) — al
   coincidir el email, WordPress lo asigna como admin automáticamente.
3. Emite un **Application Password** de WordPress para ese usuario
   (`wp user application-password create`) — es lo que autentica al
   `Connector` WooCommerce (Basic auth: usuario + application password,
   encaja tal cual con `AuthBuilder` genérico de Aero.Connector).
4. Registra los webhooks de producto/cliente en WooCommerce
   (`POST /wp-json/wc/v3/webhooks`, vía `WooCommerceDriver::registerWebhook()`).
5. Llama a `Classes\DomainRouter::pointToWordPress()`.

## Sync en tiempo real

Un solo `WebhookEndpoint` de Aero.Connector por tópico (`wpflash-product`,
`wpflash-customer`) — no uno por tenant, porque `WebhookEndpoint.slug` es
único. Todos los childsites entregan a la misma URL; el tenant se resuelve
dentro de `Classes\EventListeners` por el header `X-WC-Webhook-Source`, y la
firma HMAC se verifica ahí mismo contra el secreto **de ese tenant**
(`credentials.webhook_secret` del Connector WooCommerce del tenant) — el
`WebhookEndpoint` queda con `verification: none` porque el verificador
genérico de Aero.Connector no soporta el formato de firma de WooCommerce
(base64, sin prefijo `sha256=`).

Como respaldo, `wpflash:sync` (cron cada 15 min) reconcilia lo que un webhook
perdido no haya traído.

## Reactivar / desactivar

"Volver a la plataforma Aero" (`Classes\Provisioner::deprovision()`) revierte
el domain mapping y quita al tenant de `tenants.conf` — el childsite de
WordPress no se borra, así que se puede reactivar más adelante sin perder el
catálogo ya cargado ahí.
