---
title: Configuración del chat en vivo
sort: 30
featured: false
---
# Configuración del chat en vivo

Controla si el chat está encendido en tu sitio, qué botón se muestra y si los chats se retransmiten a WhatsApp.

**Ruta:** Livechat → Configuración · **Controlador:** `Aero\Livechat\Controllers\ChannelSettings` · **Modelo:** `ChannelSettings` · **Permiso:** `aero.livechat.manage_settings`

## Campos

| Campo | Se muestra cuando | Descripción |
|---|---|---|
| Livechat activo | Siempre | Apaga por completo el chat de tu negocio: el widget se oculta y no entran mensajes nuevos. Las conversaciones existentes se conservan |
| Modo del widget | Siempre | Livechat (gestionado por Hello), WhatsApp (icono flotante), Livechat + WhatsApp o Personalizado (código de terceros) |
| Número de WhatsApp del icono | Modo WhatsApp o ambos | Número en formato internacional, p. ej. `59170000000`. El icono abre `wa.me` en una pestaña nueva |
| Código personalizado | Modo Personalizado | HTML y scripts de terceros que se incrustan en el sitio tal cual |
| Retransmitir chats a WhatsApp | Modo Livechat o ambos | Cada mensaje del chat web se envía también a tu WhatsApp; lo que respondas vuelve al visitante |
| Cuenta de Hello | Modo Livechat o ambos | Cuenta de WhatsApp conectada en Hello (WhatsApp Web o Cloud API) |
| Número que recibe los chats | Modo Livechat o ambos | Número del agente. Para responder una conversación concreta empieza el mensaje con su código, por ejemplo `#12 hola` |

> [!WARNING]
> El **código personalizado** se ejecuta sin filtros en las páginas donde esté el script del inbox. Pega solo código de fuentes en las que confíes.

> [!TIP]
> El código para incrustar el chat se obtiene en [Inboxes](livechat-inboxes).

**Versión documentada:** 1.9.0
