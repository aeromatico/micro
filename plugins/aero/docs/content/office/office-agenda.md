---
title: Agenda
sort: 20
---
# Agenda

Calendario de citas en vista **diaria** (una columna por profesional) o **semanal** (una columna por día).

**Ruta:** `Reservas → Agenda` (`/backend/aero/office/agenda`)
**Controlador:** `Aero\Office\Controllers\Agenda`
**Permiso:** `aero.office.use`, `aero.office.reception` o `aero.office.professional`

## Filtros

| Filtro | Efecto |
|--------|--------|
| Fecha | Día (o semana que lo contiene) a mostrar. Por defecto, hoy. |
| Vista | Día o semana. |
| Sucursal | Limita a una sucursal. |
| Profesional | Limita a un profesional. |

## Qué aparece

Las citas en estado **pendiente**, **confirmada**, **en atención**, **completada** y **No asistió**, ordenadas por hora. Las canceladas y rechazadas no aparecen: liberan el horario.

> [!NOTE]
> Un profesional con cuenta de acceso solo ve su propia agenda. Recepción y administración ven a todos.

Para cambiar una cita, ábrela desde la agenda; ver [Reservas](office-reservas).

**Versión documentada:** 1.1.0
