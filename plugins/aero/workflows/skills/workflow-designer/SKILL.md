---
name: workflow-designer
description: Diseña workflows de Aero.Workflows (grafos de nodos) a partir de un encargo en lenguaje natural y los deja como BORRADOR validado, listo para que una persona lo revise y publique. Úsala cuando pidan "crear un flujo", "automatizar", "un workflow para…", "que el bot haga…", o cuando haya que convertir un proceso en nodos.
---

# workflow-designer

Conviertes un encargo en un workflow de Aero.Workflows. **Nunca publicas**: entregas un borrador (`status: draft`) que una persona revisa.

## Entrada y salida

Entrada: el encargo en lenguaje natural (qué debe pasar, cuándo, con qué datos).

Salida: **solo** un objeto JSON, sin texto alrededor:

```json
{
  "name": "Consulta de menú",
  "slug": "consulta-menu",
  "description": "Qué hace, en una frase.",
  "trigger_type": "manual | event | message | webhook",
  "trigger_config": {},
  "graph": { "nodes": [ ... ], "edges": [ ... ] },
  "expose_as_tool": false,
  "tool_description": "Solo si expose_as_tool es true: qué hace la tool, para que la IA decida cuándo usarla.",
  "tool_schema": { "type": "object", "properties": { } },
  "notes_for_reviewer": ["Qué revisar antes de publicar."]
}
```

- `slug`: minúsculas, guiones, sin espacios (`alpha_dash`).
- `status` no lo pones: siempre entra como `draft`.
- `tool_schema` describe los argumentos que la IA le pasa al flujo. En `data` del nodo se leen como `{{ trigger.<campo> }}`.

## Cómo diseñar

1. **Elige el disparador** con la tabla de abajo.
2. **Usa el catálogo** (`references/nodes.md`). Solo nodos que existan ahí. Nunca inventes un tipo, un campo ni una salida.
3. **Conecta TODAS las salidas**: una condición tiene `true` y `false`; `shop.products` tiene `found`, `empty` y `not_found`. Cada salida debe llevar a algún nodo, aunque sea a una respuesta común. Si una queda sin conectar, el validador lo rechaza, porque el flujo terminaría sin responder. Lee el catálogo antes de cada arista.
4. **Lo más simple que funcione.** Menos nodos, menos fallos. Si el flujo tiene más de 12 nodos, probablemente se puede simplificar.
5. **Plantillas:** `{{ trigger.campo }}` para lo que llegó, `{{ vars.nombre }}` para lo que guardaste con `logic.set` o `save_as`.
6. **Nodos con efectos (⚠) no van en borradores.** Si el encargo necesita cobrar, enviar un mensaje o llamar una URL, deja el paso en `notes_for_reviewer` y no lo metas al grafo.

## Disparadores

| Caso | Tipo | Cuándo |
|---|---|---|
| Lo llama la IA o una persona a mano | `manual` | Tool de IA, prueba desde el editor |
| Lo que pasa en la plataforma (pedido creado, contacto nuevo) | `event` | Reacciona a eventos `aero.*` |
| Un cliente escribe por WhatsApp | `message` | Responde o atiende mensajes entrantes |
| Un sistema externo lo llama | `webhook` | Recibe una petición firmada |

Si no está claro, usa `manual` y `expose_as_tool: true`. Es lo más seguro para empezar.

## Qué no hacer

- No prometas que el flujo se ejecutará solo: queda en borrador hasta que una persona lo publique.
- No pongas datos reales de clientes ni teléfonos dentro del grafo.
- No dupliques la lógica de un flujo existente: si el tenant ya tiene uno parecido, menciónalo en `notes_for_reviewer`.
- Si el encargo es ambiguo, no adivines: deja un flujo mínimo válido y pon la duda en `notes_for_reviewer`.

## Antes de entregar

El borrador pasa por `GraphValidator::validate($graph, draft: true)`. Si devuelve errores, los corriges y vuelves a validar. Un flujo que no valida no se entrega.

Patrones comunes: `references/patterns.md`.
