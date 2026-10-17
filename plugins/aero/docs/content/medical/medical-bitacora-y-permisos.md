---
title: Permisos y bitácora
sort: 160
---
# Permisos y bitácora

**Bitácora:** `Historia clínica → Bitácora` (`/backend/aero/medical/auditlogs`)
**Controlador:** `Aero\Medical\Controllers\AuditLogs`
**Permiso:** `aero.medical.settings`

## Quién ve qué

| Permiso | Alcance |
|---------|---------|
| `aero.medical.professional` | Contenido clínico: escribe y firma notas, ficha, archivos, documentos. |
| `aero.medical.reception` | Datos administrativos de pacientes. **No** ve el contenido clínico. En modo animal, atiende Peluquería y spa. |
| `aero.medical.use` | Administra pacientes y la bitácora; no ve el contenido clínico. |
| `aero.medical.settings` | Configuración, plantillas, catálogos, bitácora y exportación del negocio (propietario). |

Roles listos: *Historia clínica: Administrador*, *Recepcionista* y *Profesional* (conviven con los de Reservas). El propietario (tenant_admin) tiene todos.

## Bitácora

Registra quién abrió, creó, editó, eliminó, firmó, imprimió, descargó, importó o exportó, y cuándo. Guarda **solo los nombres de los campos** cambiados, nunca los valores clínicos. No se puede editar ni borrar.

**Versión documentada:** 1.12.0
