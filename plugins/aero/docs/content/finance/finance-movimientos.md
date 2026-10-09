---
title: Ingresos y egresos
sort: 10
featured: false
---
# Ingresos y egresos

Registra un cobro o un pago. Al guardar se genera el asiento en el libro diario.

**Ruta:** Finanzas → Ingresos y egresos · **Controlador:** `Aero\Finance\Controllers\Movements` · **Modelo:** `Movement` · **Permiso:** `aero.finance.use`

## Campos

| Campo | Obligatorio | Descripción |
|---|---|---|
| Tipo | Sí | Ingreso o egreso (por defecto: ingreso) |
| Fecha | Sí | Fecha del movimiento |
| Monto | Sí | Importe, con dos decimales |
| Moneda | No | Por defecto BOB |
| Tipo de cambio a BOB | No | Vacío = el del día (USD) o 1 (BOB) |
| IVA incluido en el monto | No | Si el monto incluye IVA 13 %, indica cuánto; se separa en el asiento |
| Categoría | Sí | Cuenta contable; las opciones dependen del tipo |
| Cobrado / pagado en | Sí | Cuenta de caja o banco |
| Descripción | Sí | Texto libre |
| Cliente / proveedor | No | |
| NIT / CI | No | |
| N.º de factura o recibo | No | |

> [!TIP]
> Cambia primero el **Tipo**: la lista de categorías se actualiza según sea ingreso o egreso.

> [!NOTE]
> Los movimientos de la tienda, el POS y el gimnasio pueden aparecer solos si el registro automático está activo en [Configuración](finance-configuracion).

**Versión documentada:** 1.3.0
