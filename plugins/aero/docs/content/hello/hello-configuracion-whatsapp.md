---
title: Configuración de WhatsApp
sort: 80
featured: false
---
# Función: Configuración de WhatsApp

**Ruta:** `Hello → Configuración de mensajería` (también en el menú central *Configuración*)
**Controlador:** `Aero\Hello\Controllers\ChannelSettings`
**Modelo:** `Aero\Hello\Models\TenantSettings`
**Permiso:** `aero.hello.manage_settings`

Define qué cuenta de WhatsApp usa tu sitio **por defecto** y, opcionalmente, a qué
servidor tuyo se envían los eventos de mensajería (webhook).

## Canal de WhatsApp por defecto

El selector lista tus **cuentas reales de WhatsApp**, no solo un tipo de canal:

- las que conectaste por QR o código de emparejamiento (WhatsApp Web, ver [Conectar cuenta](hello-connect));
- las que te fueron asignadas (WhatsApp Cloud API).

Cada opción muestra el nombre de la cuenta, su tipo (*WhatsApp Web* o *WhatsApp Cloud API*) y,
si no está conectada, su estado.

| Campo | Descripción |
|---|---|
| Canal de WhatsApp por defecto | Cuenta que se usa para notificaciones, recordatorios de cobranza, la API y todo envío que no indique una cuenta concreta. |

> [!NOTE]
> Si la cuenta elegida se desconecta, se usa otra cuenta habilitada.

> [!TIP]
> Si no ves ninguna opción, aún no tienes cuentas: conéctalas primero en [Conectar cuenta](hello-connect).

## Tu app: API y webhooks

Para crear tus propias aplicaciones sobre tu WhatsApp: envía y lee con la API (con una key de
*Configuración → API keys*) y recibe en tu servidor los mensajes entrantes y sus estados.

| Campo | Descripción |
|---|---|
| Enviar eventos a mi app (webhook) | Activa o desactiva el envío. |
| Eventos | `message.received` (llega un mensaje de un contacto) y `message.status` (un mensaje enviado cambia a enviado, entregado, leído o fallido). |
| URL del webhook | Solo `https` y un servidor público. Debe responder con cualquier 2xx en menos de 8 segundos. |

Reglas al guardar:

- Para activar el webhook debes indicar la URL y elegir al menos un evento.
- La URL se valida siempre que la escribas, esté activo o no el webhook.
- Al guardar por primera vez se crea un **secreto** de firma. Puedes regenerarlo; el anterior deja de valer.
- Hay un botón de **prueba** que envía un evento de ejemplo a tu URL (debes guardarla antes) y un registro de las últimas entregas.
- Al reactivar el webhook a mano se limpia el aviso de desactivación automática.

**Versión documentada:** 1.28.0
