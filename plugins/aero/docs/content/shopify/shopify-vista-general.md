---
title: Shopify · Vista general
sort: 1
---
# Shopify · Vista general

Cobra los pedidos de tu tienda Shopify con **QR bancario** (Aero.Pay) y deja que el pedido se marque como pagado de forma automática cuando el banco confirma el pago.

**Ruta:** `Shopify` (menú lateral: **Tiendas** y **Pedidos**)
**Permiso:** `aero.shopify.use`

## Cómo funciona

1. En Shopify creas un **método de pago manual** (por ejemplo «QR Bolivia»).
2. En Market registras tu tienda en [Tiendas](shopify-tiendas) y la enlazas con una cuenta bancaria de [Bolivia Pay](pay-cuentas-bancarias).
3. Pegas en Shopify la URL del webhook `orders/create` que te muestra la tienda.
4. Cuando un cliente compra con ese método de pago, Shopify avisa a Market, que emite el QR del pedido.
5. El cliente ve el QR en una página pública, paga, y cuando el banco confirma el pedido se marca como **pagado** en Shopify.

Cada tenant solo ve sus propias tiendas y pedidos. Puedes seguir el estado de cada cobro en [Pedidos](shopify-pedidos).

> [!NOTE]
> Esta integración requiere el módulo de pagos (Aero.Pay). Sin él, los pedidos quedan en estado **Error** y no se emite ningún QR.

**Versión documentada:** 1.0.0
