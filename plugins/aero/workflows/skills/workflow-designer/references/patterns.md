# Patrones de diseño de flujos

Cada patrón usa solo nodos que existen en `nodes.md` y **se valida automáticamente** (si un patrón deja de ser válido, la prueba del plugin falla). Los IDs de nodo son ejemplos; cualquier id único sirve. Los patrones 3 a 6 son workflows completos (`trigger_type`, `trigger_config`, `graph`).

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

## 3. Responder a un cliente que escribe (texto)

La forma más simple de atender: un mensaje con una palabra concreta y una respuesta. `action.reply` contesta al remitente por la misma cuenta, sin necesitar su teléfono. Sin mensaje entrante (prueba manual) solo muestra lo que diría.

```json
{
  "name": "Horarios de atención",
  "trigger_type": "message",
  "trigger_config": {"keyword": "horario"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "action.reply", "data": {"body": "Atendemos de lunes a viernes de 9:00 a 18:00. ¿Te ayudo con algo más?"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

Notas: `keyword` acota el disparador; sin él responde a **todo** mensaje entrante.

## 4. Menú con botones + una rama por botón (varios workflows)

El flujo **no espera** la respuesta: lo que el cliente toca llega como un mensaje nuevo con `interactive_id`. Por eso son tres workflows: el que muestra los botones y uno por cada botón, filtrado por `interactive_id`. Requiere cuenta de API oficial y la ventana de 24 h (el cliente acaba de escribir).

**4A · Muestra el menú**

```json
{
  "name": "Bienvenida – menú",
  "trigger_type": "message",
  "trigger_config": {"keyword": "hola"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "hello.reply_buttons", "data": {
        "body": "¡Hola! ¿En qué te ayudo?",
        "buttons": "Ver precios | precios\nHablar con un asesor | asesor"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**4B · Atiende «Ver precios»**

```json
{
  "name": "Bienvenida – precios",
  "trigger_type": "message",
  "trigger_config": {"interactive_id": "precios"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "action.reply", "data": {"body": "Nuestros planes empiezan en Bs 99 al mes. ¿Quieres que te cuente cuál te conviene?"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**4C · Atiende «Hablar con un asesor»** (igual que 4B con `"interactive_id": "asesor"` y un texto como «Listo, un asesor te escribirá en unos minutos.»).

Notas: los ids (`precios`, `asesor`) se escriben en el botón con `Texto | id`. Si hay más de 3 opciones, usa el patrón 5.

## 5. Menú desplegable + un solo workflow que atiende todas las opciones

Cuando las opciones son muchas pero las respuestas son parecidas, un único workflow con Condiciones encadenadas ordena mejor que uno por opción. Cada Condición compara el id de la opción tocada.

**5A · Muestra el menú** (hasta 10 opciones; `Título | Descripción | id`, descripción e id opcionales)

```json
{
  "name": "Carta – menú",
  "trigger_type": "message",
  "trigger_config": {"keyword": "carta"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "hello.reply_list", "data": {
        "body": "Elige qué quieres ver:",
        "list_button": "Ver carta",
        "list_section": "Nuestra carta",
        "list_rows": "Pizzas | Desde Bs 40 | pizzas\nPastas | | pastas\nBebidas || bebidas"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**5B · Atiende cualquiera de las tres opciones**

```json
{
  "name": "Carta – respuestas",
  "trigger_type": "message",
  "trigger_config": {"interactive_id": ["pizzas", "pastas", "bebidas"]},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "logic.condition", "data": {"left": "{{ trigger.data.0.provider_payload.interactive_id }}", "op": "eq", "right": "pizzas"}},
      {"id": "n3", "type": "action.reply", "data": {"body": "Pizzas: margarita Bs 40, pepperoni Bs 48, hawaiana Bs 45."}},
      {"id": "n4", "type": "logic.condition", "data": {"left": "{{ trigger.data.0.provider_payload.interactive_id }}", "op": "eq", "right": "pastas"}},
      {"id": "n5", "type": "action.reply", "data": {"body": "Pastas: boloñesa Bs 38, carbonara Bs 42."}},
      {"id": "n6", "type": "action.reply", "data": {"body": "Bebidas: gaseosa Bs 8, jugo natural Bs 12."}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "target": "n2"},
      {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "true"},
      {"id": "e3", "source": "n2", "target": "n4", "sourceHandle": "false"},
      {"id": "e4", "source": "n4", "target": "n5", "sourceHandle": "true"},
      {"id": "e5", "source": "n4", "target": "n6", "sourceHandle": "false"}
    ]
  }
}
```

Notas: la última rama `false` es la opción restante; las dos salidas de cada Condición están conectadas. Los precios del ejemplo son inventados: usa los reales de la persona.

## 6. Pedir la ubicación y comprobar la cobertura (delivery)

Dos workflows: uno pide la ubicación con el botón nativo de WhatsApp y otro procesa la que llega. La ubicación compartida entra como un mensaje cuyo texto empieza con 📍, así que el disparador la filtra con `keyword: "📍"`.

**6A · Pide la ubicación**

```json
{
  "name": "Delivery – pedir ubicación",
  "trigger_type": "message",
  "trigger_config": {"keyword": "delivery"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "hello.reply_location_request", "data": {"body": "Para ver si llegamos a tu zona, toca el botón y comparte tu ubicación."}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**6B · Revisa la zona** (centro y radio de ejemplo: ponle los reales)

```json
{
  "name": "Delivery – cobertura",
  "trigger_type": "message",
  "trigger_config": {"keyword": "📍"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "action.location", "data": {"center_lat": "-16.5", "center_lng": "-68.15", "radius_km": "5"}},
      {"id": "n3", "type": "logic.condition", "data": {"left": "{{ vars.ubicacion.in_zone }}", "op": "eq", "right": "1"}},
      {"id": "n4", "type": "action.reply", "data": {"body": "¡Sí llegamos a tu zona! Estás a {{ vars.ubicacion.distance_km }} km."}},
      {"id": "n5", "type": "action.reply", "data": {"body": "Por ahora no llegamos a esa zona, pero puedes recoger tu pedido en el local."}},
      {"id": "n6", "type": "action.reply", "data": {"body": "No pude leer tu ubicación. ¿La compartes de nuevo con el botón?"}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "target": "n2"},
      {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "found"},
      {"id": "e3", "source": "n2", "target": "n6", "sourceHandle": "not_found"},
      {"id": "e4", "source": "n3", "target": "n4", "sourceHandle": "true"},
      {"id": "e5", "source": "n3", "target": "n5", "sourceHandle": "false"}
    ]
  }
}
```

## Lista de revisión antes de guardar

- [ ] La persona confirmó el plan y lo guardaste en `agreed_plan`.
- [ ] Un solo disparador y coincide con `trigger_type`.
- [ ] El disparador de mensaje está **acotado** (keyword, cuenta o `interactive_id`).
- [ ] Cada condición tiene `true` y `false` conectados; cada salida de cada nodo está conectada.
- [ ] Ninguna rama paralela se une (solo se unen ramas de una Condición).
- [ ] Todas las plantillas `{{ }}` están cerradas y apuntan a datos que existen.
- [ ] Botones/menús: cuenta de API oficial, textos dentro de los límites, ids legibles y un workflow que atienda cada opción.
- [ ] El flujo siempre termina en una respuesta o en un resultado guardado; hay salida de escape.
- [ ] Si es tool de IA, `tool_schema` describe exactamente los campos que usa `{{ trigger.* }}`.
- [ ] `workflows_validate` devolvió `valid: true`.
