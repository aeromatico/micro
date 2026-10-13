---
title: Notificaciones de reservas
sort: 110
---
# Notificaciones de reservas

Desde la versión 1.1.0, Reservas avisa por **WhatsApp** (a través de Hello) o **correo**, mediante el módulo Notify.

**Configuración:** `Reservas → Configuración → Notificaciones al cliente` (ver [Configuración](office-configuracion))

## Qué recibe el cliente

| Aviso | Cuándo |
|-------|--------|
| Acuse | Reservó en línea y la cita queda pendiente de aprobación. |
| Confirmación | La cita quedó confirmada. |
| Rechazo | El negocio rechazó la cita (incluye el motivo). |
| Cancelación | El personal canceló la cita. |
| Reprogramación | El personal cambió fecha, hora o profesional. |
| Recordatorio | Las horas indicadas antes de la cita. |

## Qué recibe el negocio

- Reserva nueva hecha por un cliente en línea.
- Cancelación o reprogramación hecha por el cliente.

## Reglas

- Al cliente le llega solo lo que **él no hizo**: lo que inicia el cliente avisa al negocio; lo que hace el personal avisa al cliente.
- El interruptor **Avisar al cliente por WhatsApp/correo** apaga los avisos al cliente; los avisos al negocio se mantienen.
- El **recordatorio** se envía solo a citas confirmadas, una vez por cita, cuando faltan las horas configuradas (0 = sin recordatorio). Si la cita se reservó dentro de esa ventana, no se envía recordatorio porque la confirmación ya fue el aviso.
- El mensaje incluye el código de reserva, servicio, profesional, sucursal, fecha y hora y el enlace para gestionar la cita.
- Un aviso no se repite para la misma cita y la misma hora.

> [!NOTE]
> Los recordatorios se revisan cada 10 minutos.

> [!IMPORTANT]
> Para WhatsApp necesitas una cuenta conectada en Hello; sin ella, el aviso sale por correo. Las reglas y plantillas de cada aviso se administran en Notify.

**Versión documentada:** 1.1.0
