---
title: Ausencias y feriados
sort: 80
---
# Ausencias y feriados

Bloqueos de agenda: vacaciones, descansos, ausencias de un profesional o cierres de todo el negocio.

**Ruta:** `Reservas → Ausencias y feriados` (`/backend/aero/office/timeoffs`)
**Controlador:** `Aero\Office\Controllers\TimeOffs`
**Modelo:** `Aero\Office\Models\TimeOff`
**Permiso:** `aero.office.use`

## Campos

| Campo | Descripción |
|-------|-------------|
| Tipo | Feriado / cierre, Vacaciones, Descanso o Ausencia (por defecto, Ausencia). |
| Motivo | Texto opcional. |
| Profesional | Vacío = aplica a todo el negocio (feriado, cierre). |
| Sucursal | Vacío = todas las sucursales. |
| Desde (obligatorio) | Fecha y hora de inicio. |
| Hasta (obligatorio) | Fecha y hora de fin; debe ser posterior a "Desde". |

## Comportamiento

El rango bloqueado deja de ofrecerse como horario disponible, tanto en el portal como en la creación manual de reservas.

> [!NOTE]
> Crear una ausencia no cancela ni mueve las citas ya existentes en ese rango; reprograma o cancela esas citas desde [Reservas](office-reservas).

**Versión documentada:** 1.1.0
