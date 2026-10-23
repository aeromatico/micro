---
title: Pedidos
sort: 20
---
# Lista: Pedidos

**Ruta:** `Shopify → Pedidos`
**Controlador:** `Aero\Shopify\Controllers\Orders`
**Modelo:** `Aero\Shopify\Models\Order`
**Permiso:** `aero.shopify.use`

Lista de solo lectura de los pedidos de Shopify que recibieron (o intentaron recibir) un QR de cobro.

## Columnas

| Columna | Qué muestra |
|---------|-------------|
| **Pedido** | Número del pedido en Shopify (buscable). |
| **Tienda** | Tienda de origen. |
| **Correo** | Correo del cliente (buscable). |
| **Monto** / **Moneda** | Total del pedido. |
| **Estado** | Ver tabla de abajo. |
| **Detalle** | Mensaje del error, si lo hubo. |
| **Creado** | Fecha de recepción. |

## Estados

| Estado | Significado |
|--------|-------------|
| `pending` | QR emitido, esperando el pago. |
| `paid` | El banco confirmó y el pedido se marcó como pagado en Shopify. |
| `cancelled` / `expired` | El QR se anuló o venció. |
| `skipped` | Moneda no soportada (solo BOB y USD). |
| `error` | No se pudo emitir el QR o marcar el pago; el motivo está en **Detalle**. |

El pedido es idempotente: si Shopify repite el aviso, no se crea otro QR.

> [!WARNING]
> Un pedido en **error** por «la tienda no tiene una cuenta bancaria activa» se corrige asignando una cuenta en [Tiendas](shopify-tiendas); los pedidos ya fallidos no se reintentan solos.

Relacionado: [Tiendas](shopify-tiendas)

**Versión documentada:** 1.0.0
