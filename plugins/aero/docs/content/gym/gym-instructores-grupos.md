---
title: Instructores y grupos
sort: 70
---
# Formularios: Instructor y Grupo

**Ruta:** `Gimnasio → Instructores` y `Gimnasio → Grupos`
**Controladores:** `Aero\Gym\Controllers\Instructors`, `Aero\Gym\Controllers\Groups`
**Modelos:** `Aero\Gym\Models\Instructor`, `Aero\Gym\Models\Group`
**Permiso:** `aero.gym.use`

## Instructor

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Obligatorio. |
| **Activo** | Los inactivos no se asignan a clases nuevas. |
| **Teléfono** y **Correo** | Contacto. |
| **Presentación** | Texto breve sobre el instructor. |

Se asigna a [horarios, clases y rutinas](gym-clases).

## Grupo

Un grupo agrupa socios con un fin común: *Competidores*, *Adulto mayor*, *Plan corporativo*…

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Obligatorio. |
| **Para qué sirve** | Propósito del grupo. |
| **Activo** | Habilita o pausa el grupo. |
| **Descripción** | Detalle. |
| **Socios del grupo** | Integrantes. |

**Versión documentada:** 1.3.0
