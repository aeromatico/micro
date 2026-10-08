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
