---
title: Módulo de pago para Odoo 17
sort: 95
featured: false
---
# Módulo de pago para Odoo 17

Si vendes con el e-commerce de **Odoo 17** (`website_sale`), el módulo
**payment_aeropay** agrega a tu tienda un método de pago que cobra con el
**QR bancario** de Bolivia Pay. No necesitas migrar tu tienda.

El módulo viene en la carpeta `integrations/odoo/payment_aeropay` (con su
README) y se descarga/solicita a soporte.

## Cómo funciona

1. El cliente confirma el pedido en el checkout de Odoo.
2. Odoo pide el QR a Bolivia Pay (`POST /api/v1/pay/qr`).
3. El cliente ve el QR en la página `/payment/aeropay/pay` de tu Odoo y lo escanea desde su app del banco.
4. El pago se confirma por **webhook firmado** (HMAC-SHA256) en `/payment/aeropay/webhook`. Como respaldo, la página consulta el estado cada 4 segundos.

> [!NOTE]
> Ambos caminos vuelven a consultar el estado del QR en la API; Odoo no se fía del contenido del webhook.

## Requisitos

- Odoo 17 con `website_sale`.
- Una [cuenta bancaria](pay-cuentas-bancarias) activa en Bolivia Pay.
- Una **API key** con los permisos `qrbo.qr.create` y `qrbo.qr.read` (ver [Tokens de API](pay-tokens-api)).
- Pedidos en **BOB** o **USD** (otras monedas no están disponibles).

## 1. Instalar el módulo en Odoo

1. Copia la carpeta `payment_aeropay` a los addons de tu Odoo y reinicia el servidor.
2. Activa el modo desarrollador y ve a **Aplicaciones → Actualizar lista de aplicaciones**.
3. Instala **Pasarela de pago: Market Pay**.

## 2. Crear la API key en Bolivia Pay

1. Entra a **Pay → Tokens de API** y crea un token nuevo.
2. Dale los permisos `qrbo.qr.create` y `qrbo.qr.read`.
3. Copia el token; lo usarás en Odoo.

## 3. Configurar el proveedor en Odoo

En **Facturación → Configuración → Proveedores de pago → Market Pay (QR Bolivia)** completa:

| Campo | Qué poner |
|-------|-----------|
| **URL de la API** | `https://market.com.bo/api/v1/pay` |
| **API key** | El token del paso 2. |
| **ID de la cuenta de Pay** | El id de la cuenta bancaria a usar. Vacío = tu cuenta de producción más antigua. |
| **Secreto del webhook** | Una cadena secreta; debe ser **igual** a la de la cuenta en Bolivia Pay (paso 4). |

Pulsa **Probar conexión** y, si responde bien, cambia el estado a **Habilitado**.

## 4. Configurar el webhook saliente en la cuenta de Pay

En la [cuenta bancaria](pay-cuentas-bancarias) que usa el módulo, completa:

- **Webhook saliente (URL):** `https://TU-ODOO/payment/aeropay/webhook`
- **Secreto del webhook:** exactamente el mismo valor que pusiste en Odoo.

Así el banco confirma el pago a Odoo al instante. Si no lo configuras, el sondeo desde la página del QR confirma el pago igual, solo que unos segundos después.

## Cambios de la API que lo hacen posible

Desde la versión 1.29.0, `POST /api/v1/pay/qr` acepta tres campos opcionales:

| Campo | Para qué sirve |
|-------|----------------|
| `return_url` | URL a la que se devuelve al cliente tras pagar (con proveedores de redirección). |
| `cancel_url` | URL a la que vuelve si cancela. |
| `payer_email` | Correo del pagador. |

La respuesta incluye además `flow` (cómo se paga: QR o redirección) y `redirect_url` (a dónde enviar al cliente cuando el proveedor es de redirección).

## Límites

- Solo monedas **BOB** y **USD**.
- **Sin reembolsos** ni tokenización de tarjetas.
- Desactiva el **impuesto adicional** de Pay: el monto del QR debe igualar al del pedido de Odoo; si no, se rechaza.

> [!TIP]
> Si algo no confirma, revisa primero que el secreto del webhook sea idéntico en Odoo y en la cuenta de Pay, y que tu Odoo sea accesible desde internet.

Ver también: [Plugin de WordPress](pay-plugin-wordpress), [Tokens de API](pay-tokens-api).

**Versión documentada:** 1.29.0
