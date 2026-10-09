---
title: Chat — Vista general
sort: 10
---
# Chat — Vista general

**Chat** es la capa que alimenta la **app de mensajería multiagente** (PWA) de tu espacio. No tiene pantallas
en el panel de administración: tu equipo la usa desde la aplicación de chat, y todo lo que hace queda
registrado en las conversaciones de [Hello](hello-vista-general).

**Permiso / requisito:** el chat es una **función PRO** (`aero/chat/pwa`). Si tu plan no la incluye, el inicio de
sesión responde «El chat es parte del plan PRO».

## Cómo entra tu equipo

| Paso | Qué ocurre |
|------|-----------|
| **Espacio** | La pantalla de acceso se abre con el identificador (handle) de tu espacio y muestra su nombre. Debe estar activo. |
| **Inicio de sesión** | Cada agente entra con su **usuario y contraseña del panel**. Debe tener acceso a tu espacio. |
| **Token por dispositivo** | Cada inicio de sesión genera un token propio de ese dispositivo. Al cerrar sesión solo se invalida el de ese dispositivo. |

> [!NOTE]
> Usuario inexistente, contraseña errónea o sin acceso al espacio devuelven el mismo mensaje («Usuario o
> contraseña incorrectos»): no se revela si el usuario existe.

## Qué puede hacer un agente

- **Bandeja:** ver las conversaciones de las cuentas de tu espacio, abrir una por su código, leer mensajes y marcarlos como leídos.
- **Responder:** enviar texto, adjuntos, encuestas y ubicaciones; iniciar una conversación nueva.
- **Organizar:** archivar, silenciar y dejar **notas internas** (solo las ve el equipo).
- **Delegar** una conversación a otro agente. Al responder una conversación sin asignar, queda asignada a quien responde.
- **Respuestas rápidas** del espacio.
- **Notificaciones push** del dispositivo.
- **CRM:** ver y editar el contacto, listas, tickets, oportunidades y cobranzas desde la conversación (si tu espacio tiene CRM).
- [**Cobrar por QR**](chat-cobros-qr) con tus cuentas bancarias de Pay.
- [**Vender productos de la tienda**](chat-ventas-tienda) creando pedidos reales.

Cada acción importante (nota, delegación, cobro, pedido, pago) queda como **evento en el hilo** de la conversación.

## Créditos

Los mensajes que salen por una cuenta externa (por ejemplo WhatsApp) consumen créditos de mensajería. Si no
hay créditos, el envío se rechaza con un aviso. Un chat web propio (livechat) no consume créditos por mensaje.

**Versión documentada:** 1.2.0
