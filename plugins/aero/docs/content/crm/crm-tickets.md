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
| **Enviar a un tenant** | opcional | Déjalo en «Ninguno» para un ticket de tu propio CRM. Elige un tenant para abrir un ticket de soporte de la plataforma dirigido a uno de sus administradores. |
| **Administrador del tenant** | requerido si eliges un tenant | Aparecen los administradores del tenant elegido; verá y responderá desde Soporte. |
| **Departamento** | requerido | Área que lo atiende (con tenant de destino, los de la plataforma). |
| **Asignado a** | requerido | Integrantes del departamento elegido; si no tiene, cualquier administrador del ámbito. |
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

## Sistema de tickets del sitio

El **portal de soporte del micrositio** (`/soporte`) se enciende con el interruptor
`tickets_enabled` de CRM → Configuración y viene **apagado por defecto**. Mientras
esté apagado, Soporte no aparece en el menú ni en el pie del micrositio y sus
páginas responden 404.

> [!NOTE]
> Los tickets que abre un tenant a la plataforma (mesa de ayuda de la plataforma)
> se llevan aparte del CRM propio del tenant. Cada ticket queda vinculado al
> administrador del tenant que lo abre (o a quien el equipo le abre el ticket en
> su nombre); sus datos completan el solicitante.

**Versión documentada:** 1.14.0
