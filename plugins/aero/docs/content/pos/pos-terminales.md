---
title: Terminales
sort: 20
---
# Formulario: Terminal

**Ruta:** `Punto de venta → Terminales`
**Controlador:** `Aero\Pos\Controllers\Terminals`
**Modelo:** `Aero\Pos\Models\Terminal`
**Permiso:** `aero.pos.manage`

Una terminal es una caja o puesto de cobro. Los [turnos de caja](pos-turnos) se abren sobre una terminal, y cada terminal solo puede tener un turno abierto a la vez.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Obligatorio. Ej: Caja principal, Barra, Terraza. |
| **Código** | Si lo dejas vacío se genera a partir del nombre. |
| **Activa** | Solo las terminales activas aparecen al abrir un turno. |

> [!NOTE]
> Tu negocio recibe automáticamente la terminal **Caja 1**.

**Versión documentada:** 1.0.0
