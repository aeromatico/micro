---
title: Plantillas de nota
sort: 40
---
# Plantillas de nota

Estructura de secciones de cada tipo de nota, editable por el negocio.

**Ruta:** `Historia clínica → Plantillas de nota` (`/backend/aero/medical/notetemplates`)
**Controlador:** `Aero\Medical\Controllers\NoteTemplates`
**Modelo:** `Aero\Medical\Models\NoteTemplate`
**Permiso:** `aero.medical.settings`

Vienen por defecto:

- **Consulta:** motivo de consulta, enfermedad actual, revisión por sistemas, signos vitales, examen físico, impresión diagnóstica, plan y tratamiento.
- **Control:** motivo del control, evolución, signos vitales, examen físico, plan.

| Campo | Qué hace |
|-------|----------|
| Nombre, tipo, activa | Identifican la plantilla. |
| Secciones | En orden: título, tipo (texto o signos vitales) y líneas. Solo una sección puede ser de signos vitales. |

> [!TIP]
> Elija con cuál se abre cada tipo de nota en [Configuración](medical-configuracion). Cada nota guarda una copia de la plantilla con que se abrió.

**Versión documentada:** 1.12.0
