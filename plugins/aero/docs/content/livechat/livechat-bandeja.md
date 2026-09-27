# Bandeja de conversaciones

**Ruta:** `Livechat → Bandeja`
**Controlador:** `Aero\Livechat\Controllers\Conversations`
**Modelo:** `Aero\Livechat\Models\Conversation`
**Permiso:** `aero.livechat.manage_conversations`

La **Bandeja** reúne las conversaciones que llegan desde tus widgets. Cada una
pertenece a un visitante y a un inbox, y se puede asignar a una persona de tu
equipo.

## Listado

| Columna | Muestra |
|---------|---------|
| **Visitante** | Nombre o correo del visitante (o «Visitante #n»). |
| **Inbox** | De qué widget viene. |
| **Estado** | Abierta o Resuelta. |
| **No leídos** | Mensajes del visitante que aún no leíste. |
| **Asignado a** | Persona de tu equipo responsable. |
| **Último mensaje** | Cuándo fue el último mensaje. |

Las conversaciones se ordenan por **último mensaje** (las más recientes arriba).

## Detalle

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Visitante** | solo lectura | Quién escribe. |
| **Inbox** | solo lectura | De dónde viene. |
| **Estado** | — | **Abierta** o **Resuelta**. |
| **Asignado a** | — | Persona del sitio (o «Sin asignar»). |
| **Página de origen** | solo lectura | URL donde el visitante abrió el chat. |
| **Conversación** | — | Historial de mensajes y el cuadro de respuesta. |

## Acciones

- **Enviar**: escribe la respuesta y envíala al visitante.
- **Adjuntar**: comparte un archivo (imágenes, PDF, Word/Excel, TXT/CSV o ZIP).
- **Finalizar**: cierra la conversación y la marca como **Resuelta**.

> [!NOTE]
> Al abrir una conversación, lo que estaba **sin leer** queda leído. Mientras la
> tienes abierta se refresca sola cada pocos segundos.

> [!TIP]
> Si el visitante vuelve a escribir después de finalizada, la conversación se
> reabre automáticamente.

> [!NOTE]
> Si el inbox tiene Telegram conectado, tus respuestas del panel también se
> envían al chat de Telegram, y lo que llega por Telegram aparece aquí.
