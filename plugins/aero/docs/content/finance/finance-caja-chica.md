---
title: Caja chica
sort: 20
featured: false
---
# Caja chica

Fondos de efectivo con su propia cuenta contable: se fondean, se devuelven, se arquean y sus gastos quedan siempre en el libro diario y mayor.

**Ruta:** Finanzas → Caja chica · **Controlador:** `Aero\Finance\Controllers\PettyCash` · **Modelo:** `PettyFund` · **Permiso:** `aero.finance.use`

## Campos del fondo

| Campo | Obligatorio | Descripción |
|---|---|---|
| Nombre | Sí | Ej. «Caja chica oficina» |
| Responsable | No | Quien custodia el fondo |
| Monto fijo de reposición | No | Referencia para reponer el fondo al monto original |
| Activa | No | Activa por defecto |

## Operaciones

Fondeo, devolución, gasto y arqueo (sobrante o faltante) se hacen desde el propio fondo.

> [!IMPORTANT]
> Con Finanzas apagado en Configuración no se pueden registrar operaciones de caja chica.

**Versión documentada:** 1.3.0
