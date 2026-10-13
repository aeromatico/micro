---
title: Reservas
sort: 30
---
# Reservas

Listado y detalle de las citas del negocio. Crear una cita y cambiar su estado valida siempre la disponibilidad y deja **historial** de quién hizo cada cambio.

**Ruta:** `Reservas → Reservas` (`/backend/aero/office/bookings`)
**Controlador:** `Aero\Office\Controllers\Bookings`
**Modelo:** `Aero\Office\Models\Booking`
**Permiso:** `aero.office.use`, `aero.office.reception` o `aero.office.professional`

## Crear una reserva manual

Solo administración y recepción.

| Campo | Descripción |
|-------|-------------|
| Cliente existente | Elige un cliente del negocio. Si lo dejas vacío, completa los datos del nuevo. |
| Nombre / Teléfono / Correo del cliente nuevo | Si ya existe un cliente con ese correo o teléfono, se reutiliza. |
| Sucursal (obligatorio) | Sucursal de la cita. |
| Servicio (obligatorio) | Debe ofrecerse en la sucursal elegida. |
| Profesional | Vacío = "Cualquiera disponible": se asigna al que tenga menos carga ese día. |
| Fecha y hora (obligatorio) | Se valida contra horarios, ausencias y reservas existentes. |
| Estado inicial | Por defecto, Confirmada. |
| Notas del cliente | Texto libre. |
| Notas internas | Solo las ve el personal. |

El precio, la duración y los nombres del servicio y del profesional se **copian a la reserva**; cambiarlos después no altera las citas ya hechas.

> [!NOTE]
> Si el horario ya está ocupado, el sistema responde "Ese horario acaba de ocuparse" y no crea la cita: dos personas no pueden tomar el mismo horario.

## Estados

| Estado | Significado |
|--------|-------------|
| Pendiente | Esperando aprobación del negocio. |
| Confirmada | Aceptada. |
| En atención | La atención comenzó. |
| Completada | Terminó. Estado final. |
| Cancelada | Anulada; el horario queda libre. Estado final. |
| Rechazada | El negocio no la aceptó. Estado final. |
| No asistió | El cliente no se presentó. Estado final. |

Transiciones permitidas: Pendiente → Confirmada, Rechazada o Cancelada; Confirmada → En atención, Completada, Cancelada o No asistió; En atención → Completada o Cancelada.

## Acciones sobre una reserva

| Acción | Quién | Notas |
|--------|-------|-------|
| Confirmar | Administración / recepción | Desde Pendiente. |
| Rechazar | Administración / recepción | Admite un motivo. |
| Cancelar | Administración / recepción | Admite un motivo. |
| Iniciar atención | Además, el profesional de la cita | |
| Completar | Además, el profesional de la cita | |
| No asistió | Administración / recepción | |
| Reprogramar | Administración / recepción | Nueva fecha y hora, validada contra la disponibilidad. |
| Reasignar | Administración / recepción | Solo entre profesionales que atienden ese servicio en esa sucursal. |
| Guardar notas internas | Quien ve la reserva | Es lo único que se edita directamente. |

Una reserva en estado final ya no se puede reprogramar.

> [!TIP]
> El historial de la reserva registra creación, cambios de estado, reprogramaciones, reasignaciones y ediciones, indicando si las hizo el cliente o el personal.

Relacionados: [Agenda](office-agenda), [Notificaciones](office-notificaciones).

**Versión documentada:** 1.1.0
