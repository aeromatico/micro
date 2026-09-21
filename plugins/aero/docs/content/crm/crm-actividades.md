# Formulario: Actividades

**Ruta:** `CRM → Actividades`
**Controlador:** `Aero\Crm\Controllers\Activities`
**Modelo:** `Aero\Crm\Models\Activity`
**Permiso:** `aero.crm.manage_activities`

Registra y agenda el **seguimiento**: llamadas, correos, mensajes, reuniones,
notas y tareas. Es lo que convierte un CRM en una herramienta de trabajo diario.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Tipo** | Llamada, Email, WhatsApp, Reunión, Nota o Tarea. |
| **Vencimiento** | Fecha y hora límite. |
| **Asunto** | requerido. Resumen de la actividad. |
| **Descripción** | Detalle. |
| **Responsable** | Usuario del backend a cargo. |
| **Estado** | Pendiente, En curso, Completada o Cancelada. |

## Acciones rápidas

| Acción | Qué hace |
|--------|----------|
| **Cambiar estado** | Actualiza el estado sin abrir la ficha. |
| **Completar** | Marca la actividad como completada de un clic. |

> [!TIP]
> Agenda siempre la **próxima acción** de cada negocio del pipeline. Así el
> seguimiento no depende de la memoria.
