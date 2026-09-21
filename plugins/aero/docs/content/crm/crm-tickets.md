# Formulario: Tickets

**Ruta:** `CRM → Tickets`
**Controlador:** `Aero\Crm\Controllers\Tickets`
**Modelo:** `Aero\Crm\Models\Ticket`
**Permiso:** `aero.crm.manage_tickets`

Mesa de ayuda: registra y da seguimiento a las solicitudes de tus clientes. Cada
ticket pertenece a un **departamento** y se asigna a una persona.

## Pestaña principal

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Asunto** | requerido | Resumen del ticket. |
| **Departamento** | requerido | Área que lo atiende. |
| **Asignado a** | — | Solo integrantes del departamento elegido. |
| **Prioridad** | — | Baja, Normal, Alta o Urgente. |
| **Estado** | — | Abierto, En espera, Resuelto o Cerrado. |
| **Descripción** | — | Detalle del problema. |

## Pestaña Solicitante

| Campo | Para qué sirve |
|-------|----------------|
| **Solicitante** | Nombre de quien escribe. |
| **Correo** | Email del solicitante. |
| **Teléfono / WhatsApp** | Contacto directo. |
| **Contacto del CRM** | Vínculo al contacto del CRM, si existe. |

## Pestaña Respuestas

- **Conversación**: historial de respuestas del ticket.
- **Nueva respuesta**: escribe una respuesta y se agrega a la conversación.
- **Nota interna**: si la marcas, el solicitante **no** la ve (solo el equipo).

## Estados y prioridades

| Estado | Significado |
|--------|-------------|
| `open` | Abierto, sin resolver. |
| `pending` | En espera de información o de terceros. |
| `resolved` | Resuelto. |
| `closed` | Cerrado. |

| Prioridad | Uso |
|-----------|-----|
| `low` / `normal` / `high` / `urgent` | Ordena la cola de atención. |

> [!NOTE]
> Al agregar la **primera respuesta pública** (no interna) se registra la
> primera atención, útil para medir tiempos de respuesta.

> [!TIP]
> Asigna **Departamento** desde el principio: define a quién puede asignarse el
> ticket y mantiene el orden.
