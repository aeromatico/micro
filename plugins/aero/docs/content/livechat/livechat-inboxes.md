# Formulario: Inboxes

**Ruta:** `Livechat → Inboxes`
**Controlador:** `Aero\Livechat\Controllers\Inboxes`
**Modelo:** `Aero\Livechat\Models\Inbox`
**Permiso:** `aero.livechat.manage_inboxes`

Un **inbox** es un widget embebible: el chat que aparece en tu web. Puedes tener
varios (por ejemplo, uno por marca o por sección) y cada uno con su propio color
y mensaje de bienvenida.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre interno del inbox (no lo ve el visitante). |
| **Color** | — | Color de la burbuja del chat. |
| **Activo** | — | Si lo apagas, deja de aceptar **mensajes nuevos**. |
| **Mensaje de bienvenida** | — | Se envía automáticamente al abrir una conversación. Vacío = no envía nada. |
| **Widget key** | solo lectura | Identificador público del widget; va en el código. No es secreto. |
| **Código para el sitio** | solo lectura | El `<script>` que pegas en tu web. |

## Pestaña Telegram (recibir avisos fuera del panel)

| Campo | Para qué sirve |
|-------|----------------|
| **Token del bot** | El token que te da [@BotFather](https://t.me/BotFather). Se guarda cifrado; en blanco significa «no cambiar el actual». |
| **Chat ID** | Chat o grupo donde quieres recibir los mensajes. El botón **Probar y conectar** lo completa solo si lo dejas vacío. |

Pasos para conectar Telegram:

1. Creá el bot con **@BotFather** y copiá el token.
2. Pegalo en **Token del bot** y guardá el inbox.
3. Agregá el bot al chat o grupo donde quieres recibir los mensajes y escribí
   cualquier cosa ahí.
4. Tocá **Probar y conectar**.

## Cómo instalar el widget en tu sitio

Pegá el **Código para el sitio** justo antes de `</head>`:

- **WordPress:** Apariencia → Editor de temas → `header.php`, o con un plugin
  tipo «Insert Headers and Footers».
- **HTML plano:** antes de `</head>` en cada página (o en el layout compartido).
- **Wix, Shopify, Squarespace, etc.:** opción «Código personalizado» /
  «Custom code» / «Insertar en &lt;head&gt;».

El chat aparece solo, como una burbuja flotante; no hace falta tocar nada más.

> [!TIP]
> Guardá el inbox primero: el código y el botón de Telegram se generan al
> guardar.

> [!WARNING]
> El **Token del bot** es un secreto: no lo compartas. La **Widget key**, en
> cambio, es pública por diseño.
