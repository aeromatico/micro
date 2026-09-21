# Formulario: Pedidos

**Ruta:** `Tienda → Pedidos`
**Controlador:** `Aero\Shop\Controllers\Orders`
**Modelo:** `Aero\Shop\Models\Order`
**Permiso:** `aero.shop.manage_orders`

Registra las compras recibidas. La mayoría de los datos del pedido son de
**solo lectura** (se generan en el checkout); lo que se edita desde aquí son
las **notas internas** y las **acciones de estado**.

## Pestaña Pedido

| Campo | Para qué sirve |
|-------|----------------|
| **Número de pedido** | Identificador del pedido (con el prefijo configurado). |
| **Estado** | Ver estados abajo. |
| **Cliente** | Comprador. |
| **Método de pago** | Cómo se paga. |
| **Referencia de pago reportada** | Referencia que dejó el comprador. |
| **Moneda / Subtotal / Total** | Importes del pedido. |
| **Comentario del cliente** | Nota del comprador. |
| **Notas internas** | **Editable**: notas para el equipo. |

Los bloques de **pago** y **dirección de envío** se muestran según el pedido.

## Pestaña Artículos

Detalle de los productos comprados: producto, variante, SKU, precio unitario,
cantidad y subtotal. Es una **instantánea** de lo comprado (no cambia si luego
editas el producto).

## Pestaña Historial

Registro de cambios de estado, con fecha y nota.

## Estados

| Estado | Significado |
|--------|-------------|
| `pending` | Pendiente. |
| `awaiting_payment` | Esperando pago. |
| `paid` | Pagado. |
| `fulfilled` | Despachado / entregado. |
| `cancelled` | Cancelado. |
| `refunded` | Reembolsado. |

## Acciones

| Acción | Qué hace |
|--------|----------|
| **Marcar como pagado** | Confirma el pago (el stock ya se reservó al crear el pedido). |
| **Marcar como despachado/entregado** | Marca el pedido como cumplido. |
| **Cancelar pedido** | Cancela y **libera el stock** reservado, con un motivo. |

> [!NOTE]
> Con una cuenta de **QR estático** (sin API de banco), el pago no se confirma
> solo: el pedido muestra un aviso para que verifiques la transacción en
> *Bolivia Pay → Cuentas → Transacciones* antes de marcar pagado.

> [!TIP]
> Al cambiar de estado se pueden enviar avisos al cliente (pago, envío,
> cancelación) por el plugin de notificaciones, si están configurados.
