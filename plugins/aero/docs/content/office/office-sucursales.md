---
title: Sucursales
sort: 70
---
# Sucursales

Los locales donde atiendes, con su dirección y horario semanal.

**Ruta:** `Reservas → Sucursales` (`/backend/aero/office/branches`)
**Controlador:** `Aero\Office\Controllers\Branches`
**Modelo:** `Aero\Office\Models\Branch`
**Permiso:** `aero.office.use`

## Campos

| Campo | Descripción |
|-------|-------------|
| Nombre (obligatorio) | Nombre de la sucursal. |
| Teléfono, Dirección | Se muestran al cliente. |
| Latitud, Longitud | Ubicación. |
| Activa | Desactivarla la oculta del portal; sus reservas anteriores se conservan. |
| Horario semanal | Un turno por fila: Día, Desde, Hasta. |
| Servicios que se ofrecen aquí | Servicios disponibles en la sucursal. |
| Profesionales que atienden aquí | Profesionales de la sucursal. |

## Horario semanal

- Un turno por fila; escribe las horas como `09:00`.
- Dos turnos el mismo día = descanso entre ambos (09:00–13:00 y 15:00–19:00).

> [!IMPORTANT]
> Sin horario en alguna sucursal activa, no hay horarios disponibles para reservar. Es el paso 5 de la guía del [Resumen](office-resumen).

**Versión documentada:** 1.1.0
