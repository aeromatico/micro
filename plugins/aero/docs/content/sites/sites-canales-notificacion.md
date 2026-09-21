# Formulario: Canales de notificación

**Ruta:** `Sitio Web → Configuración de sitio → pestaña Notificaciones`
**Modelo:** `Aero\Notify\Models\Channel`
**Formulario embebido:** `$/aero/notify/models/channel/inline_fields.yaml`

Define **a dónde llegan los avisos** del sitio: los mensajes del formulario de
contacto, y los eventos que el sitio emita (por ejemplo, una compra o el alta de
un tenant). Cada canal es propio de tu sitio y guarda sus propias credenciales.

> [!NOTE]
> Esta pestaña solo aparece si el plugin **Aero.Notify** está instalado. Es una
> dependencia blanda: si no está, el resto de la configuración funciona igual.

## Campos comunes

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre del canal** | Etiqueta para reconocerlo (ej: *Email principal*). |
| **Canal** | Tipo: Email, WhatsApp, Telegram o SMS. Al elegirlo se muestran sus campos. |
| **Habilitado** | Si está apagado, el canal se ignora al entregar. |

## Campos según el canal

### Email

| Campo | Para qué sirve |
|-------|----------------|
| **Email destino** | A dónde se envían las notificaciones. |
| **Host / Puerto / Cifrado** | Servidor SMTP. Opcional: vacío usa el servidor del sistema. |
| **Usuario / Contraseña (API key)** | Credenciales SMTP. |
| **Nombre del remitente** | Nombre que ve el destinatario. |

### WhatsApp

| Campo | Para qué sirve |
|-------|----------------|
| **Cuenta de WhatsApp (Hello)** | Cuenta conectada en Aero.Hello. Vacío = la primera del sitio. |
| **Número destino** | Con código de país, **sin `+`** (ej: `59170000000`). |

### Telegram

| Campo | Para qué sirve |
|-------|----------------|
| **Bot Token** | Token del bot que envía. |
| **Chat ID** | Conversación de destino. |

### SMS

| Campo | Para qué sirve |
|-------|----------------|
| **Account SID / Auth Token** | Credenciales de Twilio. |
| **Número origen** | Desde qué número sale el SMS. |
| **Número destino** | A qué número llega, con `+` y código de país. |

## Comportamiento

- **Es opt-in**: si no creas un canal, las notificaciones siguen funcionando con
  las credenciales y direcciones de la plataforma. Crear un canal solo lo
  personaliza.
- Las credenciales (`config`) se guardan **cifradas** en la base de datos.
- En el listado puedes activar/desactivar un canal sin abrirlo y eliminarlo
  directamente.

> [!CAUTION]
> Si registras un canal para un tipo (por ejemplo *Email*) y lo dejas mal
> configurado, las notificaciones de ese tipo pueden fallar en vez de caer al
> canal de plataforma. Si dudas, desactívalo en lugar de borrarlo.
