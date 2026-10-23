---
title: Tiendas
sort: 10
---
# Formulario: Tiendas

**Ruta:** `Shopify → Tiendas`
**Controlador:** `Aero\Shopify\Controllers\Stores`
**Modelo:** `Aero\Shopify\Models\Store`
**Permiso:** `aero.shopify.use`

Una **tienda** conecta tu Shopify con una cuenta bancaria de cobro. Puedes registrar más de una; cada dominio de Shopify puede conectarse una sola vez.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Cómo identificas la tienda en Market. |
| **Dominio de la tienda** | requerido; formato `mi-tienda.myshopify.com`, único | Dominio `.myshopify.com` de tu tienda. Se guarda en minúsculas. |
| **Cuenta bancaria que cobra** | — | Cuenta de Bolivia Pay que emite los QR. Solo aparecen las cuentas activas **con API de banco** (no las de QR estático). |
| **Nombre(s) del método de pago manual** | — | Tal como lo creaste en Shopify, separados por coma. Solo los pedidos con ese método reciben QR (no distingue mayúsculas). |
| **Activa** | — | Una tienda inactiva no emite QR ni muestra su página de pago. |
| **Token de Admin API** | — | Token `shpat_…` de tu app de Shopify. Sirve para marcar el pedido como pagado. |
| **Secreto de la app (firma de webhooks)** | — | Sirve para comprobar que el aviso viene realmente de Shopify. |

> [!IMPORTANT]
> El token y el secreto se guardan **cifrados** y no se vuelven a mostrar. Si dejas los campos en blanco al editar, se conservan los actuales.

## Conexión en Shopify (al editar)

Al abrir una tienda ya guardada verás:

- **URL del webhook `orders/create`** (formato JSON): pégala en Shopify como webhook del tema `orders/create`.
- **Página para que el cliente vea su QR**: pégala en las instrucciones del método de pago manual. El cliente escribe número de pedido y correo y llega a su QR.
- Estado del **token** y del **secreto** (guardado / falta) y el **último error**, si lo hubo.

## Qué pedidos reciben QR

Solo los pedidos cuyo método de pago coincide con alguno de los nombres configurados y cuya moneda es **BOB** o **USD**. Otras monedas quedan como **Omitido**.

> [!TIP]
> Si no llegan pedidos, revisa el **último error** de la tienda y que el nombre del método de pago sea idéntico al de Shopify.

Relacionado: [Pedidos](shopify-pedidos) · [Vista general](shopify-vista-general)

**Versión documentada:** 1.0.0
