---
title: Ventas del POS
sort: 70
---
# Ventas del POS

**Ruta:** `Punto de venta → Ventas`
**Controlador:** `Aero\Pos\Controllers\Sales`
**Modelo:** `Aero\Pos\Models\Sale`
**Permiso:** `aero.pos.reports`

Lista de solo lectura de las ventas hechas desde el POS, ordenada de la más reciente a la más antigua, con búsqueda por número de pedido. Los cambios se hacen desde la app de venta o en [Pedidos](shop-pedidos).

## Detalle de una venta

| Sección | Contenido |
|---------|-----------|
| **Encabezado** | Número de pedido, fecha, cajero, mesa, terminal y, si existe, NIT y nombre fiscal. |
| **Productos** | Producto, cantidad e importe, con extras y notas. |
| **Totales** | Subtotal, descuento (con su motivo), propina y total. |
| **Cobros** | Por cada pago: método y referencia, monto, lo que entregó el cliente, vuelto y estado. Sin cobros, la cuenta sigue abierta. |

El botón **Ver el pedido en Tienda** abre el pedido asociado.

**Versión documentada:** 1.0.0
