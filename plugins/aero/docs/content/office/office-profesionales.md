---
title: Profesionales
sort: 60
---
# Profesionales

Las personas que atienden: su contacto, horario propio y qué servicios realizan.

**Ruta:** `Reservas → Profesionales` (`/backend/aero/office/workers`)
**Controlador:** `Aero\Office\Controllers\Workers`
**Modelo:** `Aero\Office\Models\Worker`
**Permiso:** `aero.office.use`

## Campos

| Campo | Descripción |
|-------|-------------|
| Nombre (obligatorio) | Nombre del profesional. |
| Cargo / especialidad | Se muestra en el portal. |
| Teléfono, Correo | Contacto. |
| Cuenta de acceso al panel | Opcional. Con cuenta (rol Profesional) verá solo sus reservas; sin cuenta solo figura en la agenda. |
| Presentación | Texto breve para el portal. |
| Activo | Un profesional inactivo no recibe reservas. |
| Visible para elegir en el portal | Si está apagado, el cliente no lo puede elegir, aunque puede asignársele una cita. |
| Horario individual | Turnos por día (Día, Desde, Hasta). |
| Sucursales donde atiende | Sucursales asignadas. |
| Servicios que realiza | Servicios asignados. |

## Horario individual

- Vacío: el profesional sigue el horario de la sucursal.
- Con turnos: se aplica **dentro** del horario de la sucursal.
- Dos turnos el mismo día generan un descanso entre ambos (por ejemplo 09:00–13:00 y 15:00–19:00).
- Las horas se escriben como `09:00`.

> [!TIP]
> Si el profesional tiene vacaciones o una ausencia, regístrala en [Ausencias y feriados](office-ausencias-feriados) en lugar de modificar su horario.

**Versión documentada:** 1.1.0
