---
title: Punto de venta — Vista general
sort: 0
---
# Punto de venta — Vista general

**Punto de venta (POS)** convierte tu [Tienda](shop-vista-general) en una caja para vender en mostrador o en mesas: cobros divididos en varios métodos, turnos de caja con arqueo y reporte de ventas.

> [!IMPORTANT]
> El POS vende a través de la tienda. Mientras la tienda esté desactivada (*Tienda → Configuración de tienda*), el menú del POS solo muestra **Configuración**.

## Menú

| Sección | Para qué sirve | Permiso |
|---------|----------------|---------|
| [Ventas](pos-ventas) | Lista y detalle de las ventas hechas desde el POS. | `aero.pos.reports` |
| [Turnos de caja](pos-turnos) | Abrir y cerrar turnos, movimientos de efectivo y arqueo. | `aero.pos.use` o `aero.pos.manage_shifts` |
| [Mesas](pos-mesas) | Mesas del local. | `aero.pos.manage` |
| [Métodos de pago](pos-metodos-pago) | Cómo se cobra en caja. | `aero.pos.manage` |
| [Terminales](pos-terminales) | Cajas físicas o puestos de cobro. | `aero.pos.manage` |
| [Cajeros](pos-cajeros) | Personas que venden, con PIN y rol de supervisor. | `aero.pos.manage` |
| [Configuración](pos-configuracion) | Tipo de negocio, módulos, descuentos, propinas y ticket. | `aero.pos.manage` |

## Datos que se crean solos

Al abrir cualquier pantalla del POS, si faltan, se crean: los ajustes (perfil *Restaurante* si tu tienda ya es de restaurante, de lo contrario *Tienda / comercio*), una terminal **Caja 1** y los métodos de pago **Efectivo, QR, Tarjeta y Transferencia**.

## Permisos

| Permiso | Qué permite |
|---------|-------------|
| `aero.pos.use` | Vender y cobrar en el POS. |
| `aero.pos.discount` | Dar descuentos. |
| `aero.pos.void` | Anular y devolver ventas. |
| `aero.pos.manage_shifts` | Gestionar turnos de caja (cerrar los de otras personas). |
| `aero.pos.reports` | Ver reportes de ventas y caja. |
| `aero.pos.manage` | Administrar el POS (mesas, métodos, terminales, cajeros, ajustes). |

> [!NOTE]
> La app de venta (`/pos`) es una función PRO que depende del plan contratado.

**Versión documentada:** 1.0.0

## Pedidos del local

Cuando los clientes piden desde su mesa (checkout del restaurante), la caja los ve en **Pedidos**, en la barra superior, con un contador de los que faltan por cobrar. Se buscan por su **número del día**: cada pedido recibe un número corto (1, 2, 3…) que vuelve a 1 cada día y que el cliente ve en grande; se dice y se busca como **18**. El número de pedido (PED-000018) no cambia ni se reinicia y sigue siendo el identificador único. Desde ahí puedes:

- **Ver** los platos, extras y notas del pedido.
- **Verificar pago** de un pedido con QR: la caja consulta al banco y, si ya llegó, lo marca pagado.
- **Cobrar** un pedido que pasa a pagar en efectivo: se abre el cobro con el total del pedido (cambio, NIT) y la venta queda ligada a ese mismo pedido.

### Avisos sin interrumpir la caja

- Cuando entra un pedido nuevo, la caja muestra una **tarjeta pequeña** en una esquina (con el número, la mesa y el total) y suena un **tono suave**. No tapa el ticket ni el cobro y se va sola a los pocos segundos; el contador de **Pedidos** sigue marcando los que faltan. El tono se apaga en **Ajustes**.
- Al tocar la tarjeta se abre el panel de pedidos **al lado** del catálogo (en pantallas amplias puedes seguir vendiendo mientras lo consultas).
- Si estabas armando una venta y cobras un pedido, tu venta queda **en espera** y la retomas al terminar o cancelar.

