---
title: Control de acceso
sort: 30
---
# Pantalla: Acceso

**Ruta:** `Gimnasio → Acceso` y `Gimnasio → Ingresos`
**Controladores:** `Aero\Gym\Controllers\Access`, `Aero\Gym\Controllers\AccessLogs`
**Modelo:** `Aero\Gym\Models\AccessLog`
**Permiso:** `aero.gym.use`

La pantalla de **Acceso** valida quién puede entrar. Acepta el código QR del socio, su número de carnet o su documento, y responde al instante **Permitido** o **Denegado** con el motivo.

## Cuándo se permite

1. El sistema está **activo** (ver [Configuración](gym-configuracion)).
2. La credencial corresponde a un socio de tu gimnasio.
3. El socio está en estado **Activo**.
4. Tiene una membresía **vigente** (o dentro de los días de gracia).

Motivos de rechazo: *Sistema desactivado*, *Credencial desconocida*, *Socio suspendido/inactivo* y *Sin membresía vigente*.

## Dos modos de acceso

| Modo | Cómo funciona |
|------|---------------|
| **QR del socio** (clásico) | El socio muestra su QR y recepción lo lee con un lector. |
| **QR del gimnasio** | La pantalla de Acceso muestra un QR que **cambia solo** cada cierto tiempo; el socio lo escanea con su móvil desde su portal. |

## Asistencia automática

Si el socio tenía una clase reservada que empieza en menos de 30 minutos (o está en curso), al entrar queda marcado con **asistencia** en esa clase.

## Registro de ingresos

Cada intento queda en **Ingresos**, con método, resultado y motivo.

**Versión documentada:** 1.3.0
