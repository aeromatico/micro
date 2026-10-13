---
title: Portal público de reservas
sort: 100
---
# Portal público de reservas

Página en el dominio de tu negocio donde los clientes reservan sin intervención del personal, y "Mis reservas" para quienes tienen cuenta.

**Rutas públicas:** `/reservas`, `/reservas/{token}`, `/cuenta/reservas`
**Componentes:** `officeBooking` (reservas) y `officeAccount` (mi cuenta)
**Requisito:** [Configuración](office-configuracion) con **Publicar portal de reservas** activo

## Cómo reserva el cliente

1. Elige sucursal y servicio (y profesional, si no se permite "cualquiera").
2. Ve en un calendario los días con horarios libres y elige un horario.
3. Escribe nombre, **correo (obligatorio)**, teléfono opcional y notas.
4. Recibe un enlace personal de su cita: `/reservas/{token}`.

Solo se muestran servicios y profesionales marcados como **visibles en el portal**, y sucursales activas.

## Estado inicial de la cita

- Servicio **sin** aprobación: la cita queda Confirmada al instante.
- Servicio **con** aprobación: queda Pendiente hasta que el negocio la confirme o rechace.

## Gestionar una cita con el enlace

Desde `/reservas/{token}` el cliente puede **cancelar** o **reprogramar**, hasta las horas antes que definas en la configuración. Si el servicio requiere aprobación y reprograma una cita confirmada, vuelve a quedar Pendiente.

## Cuenta del cliente

Al reservar sin sesión, el sistema crea una cuenta con su correo y le envía un mensaje para definir su contraseña. En **Mis reservas** (`/cuenta/reservas`) ve sus próximas citas y su historial en ese negocio, y puede cancelar las abiertas. Un cliente solo ve sus reservas del negocio del dominio que visita.

## Protecciones

- Límite de 10 intentos de reserva por hora desde la misma conexión.
- Campo trampa contra robots.
- El negocio se determina por el dominio; el cliente nunca elige negocio.

> [!NOTE]
> Si el portal no está publicado, o el módulo está apagado, `/reservas` responde como página inexistente.

**Versión documentada:** 1.1.0
