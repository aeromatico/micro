---
title: Turnos de caja
sort: 60
---
# Turnos de caja y arqueo

**Ruta:** `Punto de venta → Turnos de caja`
**Controlador:** `Aero\Pos\Controllers\Shifts`
**Modelo:** `Aero\Pos\Models\Shift`
**Permiso:** `aero.pos.use` o `aero.pos.manage_shifts`

Un turno agrupa las ventas de una terminal entre la apertura y el cierre de caja, y calcula el efectivo que debería haber.

## Abrir un turno

| Campo | Para qué sirve |
|-------|----------------|
| **Caja** | Terminal activa que no tenga ya un turno abierto. Si no hay ninguna libre, la pantalla lo avisa. |
| **Efectivo inicial en caja** | Lo que hay al empezar (el «cambio» del día). |

## Detalle del turno

Muestra el estado (Abierto/Cerrado), quién abrió y cerró y cuándo, y:

- Indicadores: ventas, total vendido, cobrado, propinas, descuentos y anuladas.
- **Cobros por método**: cantidad y total por cada método.
- **Arqueo de efectivo**: efectivo inicial + cobros en efectivo + ingresos − egresos = **efectivo esperado**. Al cerrar se añaden el efectivo contado y la **diferencia**.
- **Movimientos de efectivo**: hora, tipo, motivo, quién lo registró y monto.

## Acciones con el turno abierto

| Acción | Qué hace |
|--------|----------|
| **Registrar ingreso o egreso** | Tipo, monto y motivo obligatorios (ej. compra de hielo). Ajusta el efectivo esperado. |
| **Cerrar turno** | Escribes el efectivo contado (obligatorio) y notas opcionales; el sistema calcula la diferencia con lo esperado. Pide confirmación. |

Un turno cerrado puede imprimirse con **Imprimir reporte**.

> [!IMPORTANT]
> Quien abrió el turno puede operarlo. Para registrar movimientos o cerrar el turno de otra persona se necesita `aero.pos.manage_shifts`.

> [!NOTE]
> Con **Exigir turno de caja abierto para vender** activo en la [Configuración](pos-configuracion), no se puede vender sin turno.

**Versión documentada:** 1.0.0
