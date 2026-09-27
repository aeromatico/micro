# Livechat — Vista general

El **Livechat** agrega un chat en vivo a tu sitio web: una burbuja flotante donde
los visitantes escriben y tu equipo responde desde el panel. Cada sitio (tenant)
tiene sus propios inboxes, contactos y conversaciones, aislados de los demás.

## Cómo funciona

1. Creas un **Inbox** (un widget por sitio, marca o área de atención).
2. Pegas su **código** antes de `</head>` en tu web.
3. Los visitantes escriben; las conversaciones aparecen solas en la **Bandeja**.
4. Respondes desde el panel y, si quieres, conectas un **bot de Telegram** para
   recibir y contestar sin entrar al panel.

```text
Visitante (widget en tu web)
        │  mensaje
        ▼
   Bandeja de Livechat  ──►  Telegram (opcional)
        │  respuesta del agente
        ▼
Visitante
```

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Bandeja** | Atender las conversaciones que llegan del widget. |
| **Inboxes** | Crear y configurar los widgets (nombre, color, bienvenida, Telegram). |

## Guías de cada función

- [Inboxes](livechat-inboxes) — crear el widget y conectar Telegram.
- [Bandeja de conversaciones](livechat-bandeja) — responder, adjuntar y finalizar.

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.livechat.manage_inboxes` | Inboxes |
| `aero.livechat.manage_conversations` | Bandeja |

> [!NOTE]
> Todo lo que ves aquí es de **tu sitio**. Otros negocios no ven tus inboxes ni
> tus conversaciones, y tú tampoco ves los suyos.
