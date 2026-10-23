---
title: Diezmos, ofrendas y donaciones
sort: 40
---
# Diezmos, ofrendas y donaciones

**Ruta:** `Iglesia → Aportes`, `Conceptos de aporte`, `Resumen de aportes` y `Configuración`
**Controladores:** `Contributions`, `Funds`, `Giving`, `GivingSettings`
**Modelos:** `Contribution`, `Fund`, `ChurchSettings`
**Permiso:** `aero.church.use`, `aero.church.treasury` (el resumen también `pastor`)

## Conceptos

Un **concepto** clasifica el aporte: diezmo, ofrenda, donación o campaña (con meta y fechas opcionales). Al abrir Aportes por primera vez se crean *Diezmo*, *Ofrenda* y *Donación*.

## Registrar un aporte

| Campo | Descripción |
|-------|-------------|
| **Concepto, sede, monto, moneda, fecha** | Moneda: bolivianos o dólares. |
| **Forma de pago** | Efectivo, transferencia o **QR bancario**. |
| **Aporte anónimo** | No guarda quién aportó ni emite comprobante. |
| **Miembro / aportante externo** | Quién aportó. |
| **Referencia bancaria** | Número de la transferencia. |

- **Efectivo y transferencia** quedan **confirmados** al guardar.
- **QR:** queda **pendiente**; se genera el QR con la cuenta que elijas en Configuración y se confirma solo cuando el banco avisa el pago. También puedes *Confirmar pago manualmente*.
- Un aporte confirmado **no se edita ni se elimina**: se **anula** (y se anula su asiento en Finanzas).

## Comprobante

Con **Emitir comprobante** se asigna un número correlativo por iglesia (`R-000001`) y se descarga en PDF. Los aportes anónimos no tienen comprobante.

## Resumen y privacidad

El **Resumen de aportes** muestra totales por periodo, concepto, sede y forma de pago, y la evolución de 12 meses, **sin nombres**. El pastor ve el resumen; solo tesorería y administración ven quién aportó.

## Configuración

Moneda por defecto, prefijo del comprobante y la **cuenta bancaria** de Bolivia Pay que emite los QR.

> [!NOTE]
> Con Finanzas activo, cada aporte confirmado se registra como ingreso en cuentas de Diezmos, Ofrendas o Donaciones y campañas.

**Versión documentada:** 1.6.0
