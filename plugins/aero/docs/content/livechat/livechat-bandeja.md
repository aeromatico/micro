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

## Historial completo y sesión del visitante

El **widget solo muestra al visitante los mensajes de su sesión activa**, no el
historial de sesiones anteriores. La sesión nueva empieza cuando:

- el visitante escribe de nuevo en una conversación ya finalizada, o
- retoma su chat desde otro dispositivo o navegador (se detecta por su correo o
  celular y en la conversación aparece la marca «↻ Nueva sesión del visitante»).

Tú, como agente, **sigues viendo el historial completo** en la Bandeja y en el
omnichat (Aero Chat).

## Banear a un visitante

Desde la app de Aero Chat (PWA), en una conversación del canal de chat web,
tienes dos acciones propias de este canal:

| Acción | Qué hace |
|--------|----------|
| **Finalizar** | Cierra la conversación y la marca como **Resuelta**, igual que en la Bandeja. |
| **Banear** | Bloquea al visitante. Puedes indicar una duración en horas (de 1 a 8760) o dejarlo vacío para un **ban permanente**. |

Mientras el ban esté vigente, el visitante **no puede iniciar un chat nuevo ni
enviar mensajes o archivos** desde el widget. Un ban con duración vence solo al
cumplirse el plazo. En la conversación queda un aviso de sistema con la duración
y quién lo aplicó.

> [!WARNING]
> El ban se aplica al visitante (contacto), no solo a la conversación. Si no
> indicas horas, el bloqueo es permanente.

> [!NOTE]
> Banear y Finalizar desde la app de Aero Chat requieren que el canal de chat web
> esté conectado en Aero Chat. La Bandeja del panel no incluye el botón Banear.

**Versión documentada:** 1.6.0
