---
title: Configuración
sort: 90
---
# Configuración

Identidad del negocio, reglas de reserva, publicación del portal y avisos al cliente. Cada negocio tiene una sola configuración, que se abre directamente.

**Ruta:** `Reservas → Configuración` (`/backend/aero/office/settings`)
**Controlador:** `Aero\Office\Controllers\Settings`
**Modelo:** `Aero\Office\Models\OfficeSettings`
**Permiso:** `aero.office.settings`

## General

| Campo | Descripción |
|-------|-------------|
| Enlace público de reservas | Solo lectura: `https://<tu dominio>/reservas`. |
| Módulo activo | Apagado = no se aceptan reservas nuevas. |
| Publicar portal de reservas | Actívalo cuando tengas sucursal, servicios, profesionales y horarios. |

## Identidad del negocio

| Campo | Descripción |
|-------|-------------|
| Nombre del negocio | Aparece en el portal y en los avisos. |
| Rubro | Consultorio, veterinaria, barbería, academia… |
| Descripción | Presentación del negocio. |
| URL del logo, Color de marca | Apariencia del portal (por ejemplo `#4f46e5`). |
| Teléfono, Correo de contacto | Datos de contacto. |
| Dirección principal, Latitud, Longitud | Ubicación. |

## Reglas de reserva

| Campo | Defecto | Descripción |
|-------|---------|-------------|
| Anticipación mínima (horas) | 2 | Cuánto antes debe reservar un cliente en línea. |
| Se puede reservar hasta (días adelante) | 60 | Horizonte máximo del portal. |
| Intervalo entre horarios | 15 min | Paso con que se ofrecen los horarios. |
| El cliente puede cancelar/reprogramar hasta (horas antes) | 2 | Pasado ese límite, debe contactar al negocio. |
| Permitir «cualquier profesional disponible» | Sí | Apagado = el cliente debe elegir profesional. |

Estas reglas aplican a las reservas del portal; el personal puede crear citas manuales sin la anticipación mínima.

## Notificaciones al cliente

| Campo | Defecto | Descripción |
|-------|---------|-------------|
| Avisar al cliente por WhatsApp/correo | Sí | Acuse, confirmación, rechazo, cancelación y reprogramación. Requiere una cuenta de WhatsApp en Hello; si no, sale por correo. |
| Recordatorio (horas antes de la cita) | 24 | 0 = sin recordatorio. |

## Otros

| Campo | Descripción |
|-------|-------------|
| Moneda | Por defecto BOB. Se copia a cada reserva. |
| Términos / indicaciones para el cliente | Texto que ve el cliente al reservar. |

Ver también [Notificaciones](office-notificaciones) y [Portal público](office-portal-publico).

**Versión documentada:** 1.1.0
