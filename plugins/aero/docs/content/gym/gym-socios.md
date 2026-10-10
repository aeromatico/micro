---
title: Socios
sort: 10
---
# Formulario: Socio

**Ruta:** `Gimnasio → Socios`
**Controlador:** `Aero\Gym\Controllers\Members`
**Modelo:** `Aero\Gym\Models\Member`
**Permiso:** `aero.gym.use`

Un socio es la persona inscrita en tu gimnasio. Se crea además su usuario para que pueda entrar a su portal.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre completo** | Obligatorio. |
| **Estado** | `Activo`, `Suspendido` o `Inactivo`. Solo un socio **activo** con membresía vigente puede entrar (ver [Control de acceso](gym-acceso)). |
| **Teléfono / WhatsApp** | Con código de país (`59170000000`). Es donde llegan los recordatorios. |
| **Correo** | Se usa para su cuenta del portal. |
| **Documento** | También sirve como credencial de acceso. |
| **Nacimiento** | Fecha de nacimiento. |
| **N.º de carnet** | Opcional: carnet físico leído con lector de código. |
| **Código QR** | Se genera solo al crear al socio. Es único y se muestra en su carnet. |
| **Grupos** | Grupos a los que pertenece ([Instructores y grupos](gym-instructores-grupos)). |
| **Notas** | Observaciones internas. |

## Carnet

Desde la ficha del socio se abre su **carnet** con el código QR, listo para imprimir o enviar.

> [!TIP]
> Si el socio pierde su QR, no necesita un carnet nuevo: puede entrar con su documento o su número de carnet.

## Relacionado

[Planes y membresías](gym-membresias) · [Medidas](gym-medidas) · [Rutinas](gym-rutinas)

**Versión documentada:** 1.3.0
