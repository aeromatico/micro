---
title: Resumen
sort: 10
---
# Resumen

Pantalla de entrada del módulo: lo que pasa hoy, las solicitudes por atender y, mientras falte algo, la guía de configuración inicial.

**Ruta:** `Reservas → Resumen` (`/backend/aero/office/panel`)
**Controlador:** `Aero\Office\Controllers\Panel`
**Permiso:** `aero.office.use`, `aero.office.reception` o `aero.office.professional`

## Qué muestra

| Elemento | Detalle |
|----------|---------|
| **Hoy** | Citas del día en estado pendiente, confirmada, en atención o completada, por hora. |
| **Pendientes** | Hasta 15 solicitudes pendientes con fecha futura, para confirmarlas o rechazarlas. |
| **Contadores** | Citas de hoy, pendientes, próximas 7 días (pendientes y confirmadas) y "No asistió" de los últimos 30 días. |
| **Enlace público** | La URL `/reservas` de tu dominio, cuando el portal está publicado. |
| **Pasos de configuración** | Solo para administración y propietario. |

## Los 6 pasos de configuración

1. Completa la información del negocio ([Configuración](office-configuracion)).
2. Registra una sucursal ([Sucursales](office-sucursales)).
3. Crea tus servicios ([Servicios](office-servicios)).
4. Registra a tus profesionales ([Profesionales](office-profesionales)).
5. Configura horarios y disponibilidad (una sucursal activa con horario).
6. Publica tu enlace de reservas.

> [!NOTE]
> Un profesional solo ve las citas que le corresponden; recepción y administración ven todas las del negocio.

**Versión documentada:** 1.1.0
