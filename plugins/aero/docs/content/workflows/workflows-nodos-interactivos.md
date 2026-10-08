---
title: Nodos interactivos de Hello
sort: 25
---
# Nodos interactivos de Hello

Responden al cliente con mensajes interactivos de WhatsApp desde un workflow. Requieren el módulo Hello.

**Ruta:** editor de [Workflow](workflows-formulario-workflow)
**Permiso:** `aero.workflows.use`

| Nodo | Qué envía | Límites |
|------|-----------|---------|
| Responder con botones | Texto y de 1 a 3 botones | Botón hasta 20 caracteres. Formato por línea: `Texto` o `Texto \| id`. |
| Responder con menú | Texto y un menú desplegable | De 1 a 10 opciones; título hasta 24 y descripción hasta 72 caracteres. Formato: `Título \| Descripción \| id`. Botón del menú hasta 20 caracteres (por defecto «Ver opciones»); título de sección opcional hasta 24. |
| Responder con enlace | Texto y un botón con enlace | Texto del botón hasta 20; el enlace debe empezar con `http://` o `https://`. |
| Pedir ubicación | Texto y botón para compartir ubicación | Solo el texto. |
| Responder con botón de llamada | Texto y botón de llamada | Texto del botón opcional (hasta 20). Requiere WhatsApp Business Calling activado en el número. |

El **texto del mensaje** es obligatorio (hasta 1024 caracteres) y admite plantillas como `{{ vars.nombre }}`.

## Reglas

- Solo **responden a quien disparó el flujo** (nunca a un teléfono cualquiera).
- Solo salen con la **ventana de 24 h** abierta (último mensaje del cliente) y con una cuenta de la **API oficial** de WhatsApp. Si no, el paso falla con un error claro.
- En una prueba manual (sin mensaje entrante) no se envía nada: se muestra lo que se habría enviado.
- El editor muestra contadores y avisos de límites, y al **publicar** se validan.

## Ramificar según lo que toque el cliente

El id del botón u opción llega en el siguiente mensaje (`trigger.data.0.provider_payload.interactive_id`). También puedes crear otro workflow con disparador **Mensaje entrante** y el filtro `interactive_id` para que solo se dispare con ese botón u opción.

> [!NOTE]
> Estos nodos tienen efectos (envían mensajes), por eso un agente no puede añadirlos a un borrador automático: los agrega una persona.

**Versión documentada:** 1.6.1
