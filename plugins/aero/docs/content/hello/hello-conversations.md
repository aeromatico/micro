# Función: Bandeja de conversaciones

**Ruta:** `Hello → Bandeja de conversaciones`
**Controlador:** `Aero\Hello\Controllers\Conversations`
**Modelo:** `Aero\Hello\Models\Conversation`
**Permiso:** `aero.hello.manage_conversations`

El buzón de chats. Las conversaciones **llegan solas** cuando alguien te
escribe; aquí las lees y respondes.

## Listado

| Columna | Para qué sirve |
|---------|----------------|
| **Contacto** | Quién escribe. |
| **Cuenta** | Por qué número/cuenta entró. |
| **Proveedor** | Canal de la cuenta. |
| **Estado** | Abierta o cerrada. |
| **No leídos** | Mensajes sin leer. |
| **Último mensaje** | Cuándo fue el último movimiento. |

## Al abrir una conversación

| Campo | Para qué sirve |
|-------|----------------|
| **Contacto / Cuenta** | Contexto (solo lectura). |
| **Estado** | Puedes abrirla o cerrarla. |
| **No leídos** | Contador (se pone en cero al responder). |
| **Mensajes** | Historial de la conversación. |

## Responder

Escribe el texto y envía. La respuesta sale por la misma cuenta.

> [!WARNING]
> En cuentas con **ventana de 24 h** (Cloud API/Hello), si el contacto no
> escribió en las últimas 24 h, WhatsApp solo permite una **plantilla
> aprobada**. La bandeja lo avisa y te sugiere usar Redactar con plantilla.

> [!TIP]
> Cerrar una conversación no la borra: solo la marca como atendida. Puedes
> reabrirla cuando haga falta.
