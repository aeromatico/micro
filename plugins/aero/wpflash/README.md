# Aero.WpFlash — WordPress Flash

Motor de sitio alternativo para tenants acostumbrados a WordPress/WooCommerce.
Al activarlo, el tenant recibe un childsite en nuestro WordPress Multisite
(`wp.market.com.bo`), administrado por nosotros. Su subdominio se apunta a ese
childsite a nivel DNS (Cloudflare), y el catálogo (productos/clientes) se
sincroniza en tiempo real y en un solo sentido: **WooCommerce manda siempre**,
Aero.Shop recibe.

## Fase 1 — alcance

- ✅ Provisioning automático del childsite (nombre = subdominio del tenant,
  usuario/contraseña de admin generados, mostrados una sola vez).
- ✅ Enrutamiento del subdominio vía Cloudflare (CNAME hacia el Multisite),
  solo para subdominios de nuestra propia zona (`market.com.bo`).
- ✅ Sync en tiempo real de productos y clientes de WooCommerce → Aero.Shop,
  vía webhooks + reconciliación periódica de respaldo.
- ❌ Fuera de alcance: sync de pedidos hacia WP, dominios externos (fuera de
  nuestra zona Cloudflare — requieren apuntado manual), sync de contenido/
  páginas, cupones/reseñas de WooCommerce.

## Configuración inicial (una sola vez, manual)

Estos valores son secretos de la plataforma y **no se siembran por
migración** — se cargan a mano desde el backend:

1. **Backend → Connectors → Nuevo**: tipo "HTTP genérico" (`provider_hint =
   http`), nombre "WP Flash — Red", `base_url` = `https://wp.market.com.bo/wp-json/wpflash/v1/sites`,
   método `POST`, API Key = el secreto compartido con el mu-plugin de
   WordPress (ver abajo).
2. **Backend → Connectors → Nuevo**: tipo "Cloudflare", nombre "Cloudflare —
   market.com.bo", API Key = el email de la cuenta (`CF_API_EMAIL`), Secret =
   la Global API Key (`CF_API_KEY`), y en el JSON avanzado de configuración:
   `{"zone_id": "CF_ZONE_ID_MARKET", "account_id": "CF_ACCOUNT_ID"}`.
3. **Backend → Aero.WpFlash → Ajustes**: elige los dos Connectors recién
   creados y confirma el host de destino (`wp.market.com.bo`).

## El mu-plugin de WordPress (fuera de este repo)

WordPress Core/WooCommerce no exponen una API para crear un *childsite* de
Multisite ni para emitir claves REST de WooCommerce por red. Hace falta un
mu-plugin/plugin propio, instalado **una sola vez** en `wp.market.com.bo`
(mismo espíritu que el plugin complementario ya documentado en
`plugins/aero/docs/content/pay/pay-plugin-wordpress.md` para Aero.Pay), que
exponga:

```
POST /wp-json/wpflash/v1/sites
Authorization: Bearer <secreto compartido, el mismo del Connector de red>

{ "domain": "tenant-handle.market.com.bo", "admin_email": "admin@tenant.com" }
```

Debe, del lado de WordPress:

1. `wp_insert_site()` para crear el childsite en `domain`.
2. Activar WooCommerce en el nuevo blog.
3. Crear un usuario admin con contraseña aleatoria.
4. Emitir claves REST de WooCommerce (`consumer_key`/`consumer_secret`) para
   ese blog.

Y responder:

```json
{
  "wp_site_id": 42,
  "admin_url": "https://tenant-handle.market.com.bo",
  "admin_username": "admin",
  "admin_password": "...",
  "consumer_key": "ck_...",
  "consumer_secret": "cs_..."
}
```

Los webhooks de WooCommerce (producto/cliente) **no** hace falta registrarlos
desde el mu-plugin: `Classes\Provisioner::registerWebhooks()` los crea después
vía la API REST estándar de WooCommerce (`POST /wp-json/wc/v3/webhooks`), con
las claves recién emitidas.

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

"Volver a la plataforma Aero" (`Classes\Provisioner::deprovision()`) solo
desconecta el DNS y marca el sitio como `suspended` — el childsite de
WordPress no se borra, así que se puede reactivar más adelante sin perder el
catálogo ya cargado ahí.
