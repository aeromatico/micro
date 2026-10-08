---
title: Métodos de pago del POS
sort: 40
---
# Formulario: Método de pago

**Ruta:** `Punto de venta → Métodos de pago`
**Controlador:** `Aero\Pos\Controllers\PaymentMethods`
**Modelo:** `Aero\Pos\Models\PaymentMethod`
**Permiso:** `aero.pos.manage`

Formas de cobrar en caja. Una venta puede dividirse en varios métodos (por ejemplo, parte en efectivo y parte con QR).

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Obligatorio. Lo que ve el cajero: Efectivo, QR, Tarjeta… |
| **Tipo** | Efectivo, QR, Tarjeta, Transferencia u Otro. |
| **Código** | Si lo dejas vacío se genera a partir del nombre. |
| **Orden** | Posición en la lista (por defecto 0). |
| **Activo** | Los inactivos no se ofrecen al cobrar. |

> [!IMPORTANT]
> Solo los métodos de tipo **Efectivo** cuentan en el efectivo esperado del [arqueo de caja](pos-turnos).

> [!NOTE]
> Se crean por defecto: Efectivo, QR, Tarjeta y Transferencia.

**Versión documentada:** 1.0.0
