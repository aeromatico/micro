---
title: Reservas — vista general
sort: 0
featured: true
---
# Reservas — vista general

El módulo **Reservas** (plugin Oficina) permite que cualquier negocio con citas por fecha y hora —consultorio, veterinaria, barbería, academia— gestione su agenda: sucursales, servicios, profesionales, horarios, clientes y un portal público donde los clientes reservan solos.

**Ruta:** `Reservas` (menú lateral)
**Permisos:** `aero.office.use` (administración), `aero.office.reception` (recepción), `aero.office.professional` (profesional), `aero.office.settings` (configuración)

## Qué incluye

| Área | Para qué sirve | Artículo |
|------|----------------|----------|
| **Resumen** | Citas de hoy, solicitudes pendientes y guía de configuración inicial. | [Resumen](office-resumen) |
| **Agenda** | Vista diaria (por profesional) y semanal (por día). | [Agenda](office-agenda) |
| **Reservas** | Crear citas y llevarlas por sus estados con historial. | [Reservas](office-reservas) |
| **Clientes** | Ficha, historial y acceso al portal. | [Clientes](office-clientes) |
| **Servicios** | Duración, descanso, precio y aprobación. | [Servicios](office-servicios) |
| **Profesionales** | Quién atiende, qué servicios y su horario individual. | [Profesionales](office-profesionales) |
| **Sucursales** | Dirección y horario semanal. | [Sucursales](office-sucursales) |
| **Ausencias y feriados** | Bloqueos de agenda por vacaciones, descansos o cierres. | [Ausencias y feriados](office-ausencias-feriados) |
| **Configuración** | Identidad, reglas de reserva, portal y avisos. | [Configuración](office-configuracion) |
| **Portal público** | Reservas en línea y "Mis reservas". | [Portal público](office-portal-publico) |
| **Notificaciones** | Avisos por WhatsApp o correo a clientes y al negocio. | [Notificaciones](office-notificaciones) |

## Roles

| Permiso | Qué ve |
|---------|--------|
| Administración (`aero.office.use`) | Todo el menú excepto Configuración. |
| Recepción (`aero.office.reception`) | Resumen, Agenda, Reservas y Clientes. |
| Profesional (`aero.office.professional`) | Resumen, Agenda y Reservas, solo las citas que le corresponden. |
| Propietario (`aero.office.settings`) | Configuración del negocio y portal. |

## Puesta en marcha

El **Resumen** muestra 6 pasos con su estado real: información del negocio, una sucursal, servicios, profesionales, horarios y publicar el enlace de reservas.

## Se conecta con el resto de tu plataforma

Las conexiones son opcionales.

- **Notify / Hello:** avisos por WhatsApp o correo (ver [Notificaciones](office-notificaciones)).
- **CRM:** cada cliente se refleja como contacto del CRM, enlazado por correo o teléfono.
- **Sitios:** el portal público vive en el dominio del negocio, bajo `/reservas`.

> [!NOTE]
> Con el interruptor **Módulo activo** apagado no se aceptan reservas nuevas. Ver [Configuración](office-configuracion).

**Versión documentada:** 1.1.0
