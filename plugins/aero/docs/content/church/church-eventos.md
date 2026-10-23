---
title: Eventos, inscripción y asistencia
sort: 30
---
# Eventos, inscripción y asistencia

**Ruta:** `Iglesia → Eventos`
**Controlador:** `Events`
**Modelos:** `ChurchEvent`, `EventRegistration`
**Permiso:** `aero.church.use`, `pastor`, `secretary`

## Para qué sirve

Programa cultos, reuniones, retiros, conferencias y actividades especiales, con inscripción, cupos y control de asistencia.

## Campos

| Campo | Descripción |
|-------|-------------|
| **Título, tipo, inicio y fin, lugar** | Datos del evento. |
| **Sede, ministerio organizador, responsable** | Opcionales. |
| **Cupo máximo** | Vacío = sin límite. Al llenarse, las nuevas inscripciones pasan a **lista de espera**. |
| **Recordatorio (horas antes)** | Vacío = sin recordatorio. Se envía por WhatsApp solo a miembros que aceptaron mensajes. |
| **Inscripciones abiertas** | Interruptor. |
| **Mostrar en el sitio público** | El evento aparece en `/eventos` y admite inscripción desde la web. |
| **Estado** | Programado o cancelado. |

## Inscripción y asistencia

En la ficha del evento puedes inscribir miembros o invitados, cancelar inscripciones y registrar la llegada:

- **QR personal:** cada inscrito tiene un código; escanéalo en el campo *Escanear código QR* (sirve un lector USB).
- **Marcar llegada** con un botón, o **deshacerla**.
- **Llegó sin inscribirse:** registra su llegada ignorando el cupo.
- Si alguien cancela y había lista de espera, **sube el primero** automáticamente.

> [!TIP]
> Si un evento cambia de horario, de lugar o se cancela, se avisa por WhatsApp a los inscritos que aceptaron mensajes (se puede apagar en *Configuración → Comunicación*).

**Versión documentada:** 1.6.0
