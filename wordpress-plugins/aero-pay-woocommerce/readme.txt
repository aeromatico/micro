=== Aero Pay para WooCommerce ===
Contributors: aero
Tags: woocommerce, pagos, qr, bolivia, bnb
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Cobra con QR bancario boliviano directo en el checkout de WooCommerce, usando tu cuenta de Aero Pay.

== Description ==

Este plugin agrega una pasarela de pago a WooCommerce que genera un QR bancario dinámico (BNB, Banco Económico, etc.) por cada pedido, usando la API de Aero Pay (`api/v1/pay`).

* El cliente escanea el QR en la página de "Pedido recibido" y paga desde su app bancaria.
* El pedido pasa a "Procesando" automáticamente al instante, por webhook.
* Si el webhook no está configurado o tarda, un chequeo periódico en el navegador del cliente confirma el pago igual.
* El QR vence solo (configurable) y el pedido queda cancelado si nadie paga.

Solo soporta pagos en bolivianos (BOB): es lo único que puede confirmarse desde afuera del backend de Aero sin intervención manual.

== Instalación ==

1. Sube la carpeta `aero-pay-woocommerce` a `/wp-content/plugins/` y activa el plugin (o instala el .zip desde Plugins → Añadir nuevo).
2. En el backend de Aero: Pagos → API Tokens (o Aero.Api → API Keys), crea una key con los permisos `qrbo.qr.create`, `qrbo.qr.read` y `qrbo.qr.cancel`.
3. En WooCommerce → Ajustes → Pagos → "Aero Pay (QR bancario)", completa:
   * URL de la API: el dominio de tu tienda/panel en Aero, sin `/api` al final.
   * API key: la que creaste en el paso 2.
   * Secreto del webhook: cualquier cadena aleatoria — debe copiarse igual en el paso 4.
4. En el backend de Aero, en la cuenta bancaria a usar, completa "Outbound webhook URL" con la URL que muestra esta pantalla de ajustes, y "Outbound webhook secret" con el mismo valor del paso 3.
5. Activa la pasarela y listo.

== Preguntas frecuentes ==

= ¿Funciona con otras monedas? =

No. La API pública de Aero Pay solo permite BOB desde afuera del backend. Si tu tienda no factura en bolivianos, este método de pago no estará disponible.

= ¿Qué pasa si no configuro el webhook? =

El plugin sigue funcionando: el navegador del cliente pregunta el estado cada pocos segundos mientras espera en la página de pago. El webhook solo hace la confirmación más rápida y no depende de que el cliente deje la pestaña abierta.

== Changelog ==

= 1.0.0 =
* Primera versión: pasarela de QR bancario, confirmación por webhook y por sondeo, cancelación automática por vencimiento.
