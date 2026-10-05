# Patrones de diseño de flujos

Cada patrón usa solo nodos que existen en `nodes.md`. Los IDs de nodo son ejemplos; cualquier id único sirve.

## 1. Consulta de catálogo (tool de IA, solo lectura)

Para cuando la IA necesita responder "¿qué tienen?" o "¿cuánto cuesta X?".

```json
{
  "nodes": [
    {"id": "n1", "type": "trigger.manual", "data": {}},
    {"id": "n2", "type": "shop.products", "data": {
      "category_id": "16", "query": "{{ trigger.query }}", "limit": "10",
      "only_in_stock": "0", "show_description": "1", "save_as": "productos"}},
    {"id": "n3", "type": "action.respond", "data": {"value": "{{ vars.productos.text }}"}},
    {"id": "n4", "type": "action.respond", "data": {"value": "No hay productos disponibles para esa consulta."}}
  ],
  "edges": [
    {"id": "e1", "source": "n1", "target": "n2"},
    {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "found"},
    {"id": "e3", "source": "n2", "target": "n4", "sourceHandle": "empty"},
    {"id": "e4", "source": "n2", "target": "n4", "sourceHandle": "not_found"}
  ]
}
```

Notas:
- `shop.products` busca **dentro de una categoría**. Si el encargo es de todo el catálogo, usa la tool `shop_products_list`, no un flujo.
- **Cada salida debe estar conectada.** Si `empty` o `not_found` no tienen destino, el flujo termina sin responder y el validador lo rechaza. Aquí las dos van a una respuesta común.

## 2. Decisión con condición

Para cuando el resultado depende de un dato (por ejemplo, un monto o un plan).

```json
{
  "nodes": [
    {"id": "n1", "type": "trigger.manual", "data": {}},
    {"id": "n2", "type": "logic.condition", "data": {"left": "{{ trigger.monto }}", "op": "gt", "right": "100"}},
    {"id": "n3", "type": "logic.set", "data": {"name": "nivel", "value": "grande"}},
    {"id": "n4", "type": "logic.set", "data": {"name": "nivel", "value": "regular"}},
    {"id": "n5", "type": "action.respond", "data": {"value": "Nivel: {{ vars.nivel }}"}}
  ],
  "edges": [
    {"id": "e1", "source": "n1", "target": "n2"},
    {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "true"},
    {"id": "e3", "source": "n2", "target": "n4", "sourceHandle": "false"},
    {"id": "e4", "source": "n3", "target": "n5"},
    {"id": "e5", "source": "n4", "target": "n5"}
  ]
}
```

Notas:
- Las dos ramas se **unen** en el mismo nodo final. Así el flujo siempre termina en una respuesta.
- Si una rama no necesita hacer nada, aun así conéctala a la unión.

## 3. Flujo de mensaje de WhatsApp (cliente que escribe)

Para atender a un cliente que escribe. Este patrón responde al remitente, así que **no es un borrador automático**: `action.reply` está en ⚠. Déjalo en `notes_for_reviewer` para que lo apruebe una persona.

## Lista de revisión antes de entregar

- [ ] Un solo disparador.
- [ ] Cada condición tiene `true` y `false` conectados.
- [ ] Ninguna salida apunta a un nodo inexistente.
- [ ] Todas las plantillas `{{ }}` están cerradas.
- [ ] No hay nodos ⚠ en un borrador.
- [ ] El flujo termina en un nodo que responde o guarda un resultado.
- [ ] Si es tool, `tool_schema` describe exactamente los campos que usa `{{ trigger.* }}`.
