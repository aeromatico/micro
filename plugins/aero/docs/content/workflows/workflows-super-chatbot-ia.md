---
title: Usarlo desde el Super Chatbot IA
sort: 40
---
# Usar un workflow desde el Super Chatbot IA (opcional)

Un chatbot en modo **Super Chatbot IA** puede ejecutar tus workflows durante la conversación, por ejemplo para cotizar un envío o consultar un pedido. Es **opcional y requiere dos activaciones**:

1. **En el chatbot:** en sus herramientas de IA, marca la categoría **Workflows (automatizaciones)**.
2. **En cada workflow:** activa **Ofrecer como herramienta al Super Chatbot IA** y completa:
   - **Cuándo debe usarlo la IA:** una descripción clara.
   - **Parámetros que recibe:** un esquema JSON, por ejemplo `{"type":"object","properties":{"pedido":{"type":"string"}},"required":["pedido"]}`.

Si falta cualquiera de las dos, la IA no ve el workflow.

## Cómo funciona

- Los parámetros que decide la IA llegan al workflow en `{{ trigger.pedido }}`.
- Se ejecuta en el momento y la IA recibe lo que devuelva el nodo **Responder** (o la salida del último nodo).
- Solo se ofrecen workflows **publicados, activos y de tu cuenta** (los borradores no se ofrecen).

> [!IMPORTANT]
> La IA decide cuándo ejecutar el workflow. No expongas flujos que envíen mensajes o modifiquen datos sin revisar bien su descripción y parámetros.

> [!NOTE]
> Cada ejecución cuenta para el límite por hora y puede consumir créditos, además de los de la IA.

**Versión documentada:** 1.6.1
