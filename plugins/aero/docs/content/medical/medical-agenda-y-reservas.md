---
title: Agenda de hoy y Reservas
sort: 140
---
# Agenda de hoy y Reservas

Conexión con [Reservas](office-reservas): del turno a la nota, y de la ficha al turno.

**Ruta:** `Historia clínica → Agenda de hoy` (`/backend/aero/medical/today`)
**Controlador:** `Aero\Medical\Controllers\Today`
**Permiso:** `aero.medical.professional`

## Iniciar consulta

- En la agenda de hoy, o con el botón **Iniciar consulta** que aparece en la reserva (solo para personal clínico), desde una reserva **confirmada o en atención**.
- Crea la nota prellenada (paciente, profesional, servicio, motivo de la reserva) y enlazada a ella; la reserva pasa a **En atención**. Una reserva tiene una sola nota.
- Si el cliente no tiene ficha, en humano se crea desde sus datos; en animal, si el tutor tiene varios animales se elige cuál (o se registra uno nuevo).
- Al **firmar**, la reserva pasa a **Completada** (con el mismo servicio de Reservas, con historial).

## Agendar un control

En la ficha: sucursal, servicio, profesional (opcional), fecha y hora, y motivo. Usa la disponibilidad de Reservas: si el horario no está libre, no se agenda.

> [!NOTE]
> El motivo de la reserva lo ve recepción: debe ser breve y sin datos clínicos sensibles.

**Versión documentada:** 1.12.0
