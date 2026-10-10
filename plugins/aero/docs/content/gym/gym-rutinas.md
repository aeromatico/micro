---
title: Rutinas
sort: 50
---
# Formulario: Rutina

**Ruta:** `Gimnasio → Rutinas`
**Controlador:** `Aero\Gym\Controllers\Routines`
**Modelo:** `Aero\Gym\Models\Routine`
**Permiso:** `aero.gym.use`

Una rutina es el plan de ejercicios de un socio, organizado por día o bloque.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Obligatorio. |
| **Socio** | Sin socio, la rutina es una **plantilla** para copiar a varios. |
| **Objetivo** | Por ejemplo, hipertrofia o pérdida de peso. |
| **Instructor** | Quién la diseñó. |
| **Desde / Hasta** | Vigencia. |
| **Activa** | Solo la activa aparece en el portal del socio. |
| **Ejercicios** | Lista con día/bloque, ejercicio, series y repeticiones. |
| **Notas** | Indicaciones generales. |

> [!TIP]
> La rutina se puede **imprimir** desde la ficha para entregarla en papel.

**Versión documentada:** 1.3.0
